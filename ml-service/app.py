"""
ML Service - Deteksi Risiko Stunting dengan K-Nearest Neighbor
===============================================================
Service ini membungkus model_KNN.pkl (imblearn Pipeline) menjadi REST API
yang dipanggil oleh aplikasi web PHP.

Struktur model yang dibungkus:
    Pipeline([
        ('prep',  ColumnTransformer(num=[P4072_anak, P1509, MPASI_DIVERSITY],
                                    bin=[P916C, P404_anak],
                                    nom=[P501, P810])),
        ('smote', SMOTE(random_state=42)),          # hanya aktif saat training
        ('clf',   KNeighborsClassifier(n_neighbors=41, metric='chebyshev'))
    ])

Jalankan:
    python app.py                     (mode pengembangan, port 5000)
    waitress-serve --port=5000 app:app   (mode lebih stabil di Windows)
"""

import json
import logging
import os
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from flask import Flask, jsonify, request
from flask_cors import CORS

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "model" / "model_KNN.pkl"
CONFIG_PATH = BASE_DIR / "feature_config.json"

# Token sederhana agar endpoint tidak bisa dipanggil sembarang pihak.
# Nilai yang sama harus ditulis di web/config/config.php
API_TOKEN = os.environ.get("STUNTING_API_TOKEN", "skripsi-stunting-2026")

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("ml-service")

app = Flask(__name__)
CORS(app)

# ---------------------------------------------------------------------------
# Muat model satu kali saat service dinyalakan (bukan setiap request).
# ---------------------------------------------------------------------------
log.info("Memuat model dari %s", MODEL_PATH)
model = joblib.load(MODEL_PATH)
CONFIG = json.loads(CONFIG_PATH.read_text(encoding="utf-8"))

# Urutan kolom diambil langsung dari model, bukan ditulis manual,
# sehingga tidak mungkin salah urutan.
FEATURE_ORDER = list(model.named_steps["prep"].feature_names_in_)
NUM_COLS = ["P4072_anak", "P1509", "MPASI_DIVERSITY"]
INT_COLS = ["P916C", "P404_anak", "P501", "P810"]

log.info("Urutan fitur model: %s", FEATURE_ORDER)


def build_dataframe(payload: dict) -> pd.DataFrame:
    """Ubah JSON dari PHP menjadi DataFrame satu baris sesuai harapan model.

    Model dilatih memakai DataFrame berkolom nama, sehingga input WAJIB
    berupa DataFrame dengan nama kolom yang sama. Mengirim list/array biasa
    akan memicu error atau, lebih berbahaya, prediksi yang salah urutan.
    """
    row = {}
    for col in FEATURE_ORDER:
        if col not in payload:
            raise ValueError(f"Variabel '{col}' tidak dikirim oleh aplikasi web.")
        nilai = payload[col]
        if nilai is None or nilai == "":
            # Biarkan NaN; SimpleImputer di dalam pipeline akan mengisinya
            # dengan median/modus data training.
            row[col] = np.nan
        elif col in NUM_COLS:
            row[col] = float(nilai)
        else:
            row[col] = int(nilai)
    return pd.DataFrame([row], columns=FEATURE_ORDER)


def periksa_batas(df: pd.DataFrame) -> list:
    """Peringatan bila nilai di luar rentang data training (tetap diprediksi)."""
    catatan = []
    batas = {
        "P4072_anak": (0, 59),
        "P1509": (2.0, 35.0),
        "MPASI_DIVERSITY": (0, 6),
    }
    for col, (lo, hi) in batas.items():
        v = df.at[0, col]
        if pd.notna(v) and not (lo <= v <= hi):
            catatan.append(
                f"Nilai {col} = {v} berada di luar rentang data training ({lo}-{hi}). "
                "Hasil prediksi perlu ditafsirkan dengan hati-hati."
            )
    return catatan


@app.route("/health", methods=["GET"])
def health():
    """Dipakai PHP untuk mengecek apakah service hidup sebelum menampilkan form."""
    clf = model.named_steps["clf"]
    return jsonify({
        "status": "ok",
        "model": "KNeighborsClassifier",
        "n_neighbors": int(clf.n_neighbors),
        "metric": str(clf.metric),
        "fitur": FEATURE_ORDER,
    })


@app.route("/config", methods=["GET"])
def config():
    """Dipakai PHP untuk membangun form skrining secara dinamis."""
    return jsonify(CONFIG)


@app.route("/predict", methods=["POST"])
def predict():
    if request.headers.get("X-API-Token") != API_TOKEN:
        return jsonify({"sukses": False, "pesan": "Token tidak valid."}), 401

    payload = request.get_json(silent=True)
    if not payload:
        return jsonify({"sukses": False, "pesan": "Body JSON kosong."}), 400

    try:
        df = build_dataframe(payload)
    except (ValueError, TypeError) as exc:
        return jsonify({"sukses": False, "pesan": str(exc)}), 400

    try:
        kelas = int(model.predict(df)[0])
        proba = model.predict_proba(df)[0]
    except Exception as exc:  # noqa: BLE001
        log.exception("Prediksi gagal")
        return jsonify({"sukses": False, "pesan": f"Prediksi gagal: {exc}"}), 500

    # Dengan k=41 dan weights='uniform', probabilitas = (jumlah tetangga kelas) / 41.
    jumlah_tetangga_berisiko = int(round(proba[1] * model.named_steps["clf"].n_neighbors))

    return jsonify({
        "sukses": True,
        "hasil": kelas,
        "label": CONFIG["label_kelas"][str(kelas)],
        "probabilitas": {
            "tidak_berisiko": round(float(proba[0]), 4),
            "berisiko": round(float(proba[1]), 4),
        },
        "keyakinan_persen": round(float(max(proba)) * 100, 1),
        "tetangga_berisiko": jumlah_tetangga_berisiko,
        "total_tetangga": int(model.named_steps["clf"].n_neighbors),
        "catatan": periksa_batas(df),
        "input_diterima": df.to_dict(orient="records")[0],
    })


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=False)
