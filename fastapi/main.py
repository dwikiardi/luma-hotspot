import hashlib
import json
import os
import psycopg2
from psycopg2.extras import Json
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional, List
from datetime import datetime

from models import FingerprintPayload, FingerprintResponse

app = FastAPI(title="Luma Network Identity Engine", version="2.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

DB_HOST = os.getenv("DB_HOST", "db")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME", "luma_hotspot")
DB_USER = os.getenv("DB_USER", "postgres")
DB_PASS = os.getenv("DB_PASSWORD", "secretpassword_staging")


def get_db_connection():
    return psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        database=DB_NAME,
        user=DB_USER,
        password=DB_PASS
    )


@app.get("/health")
async def health_check():
    return {"status": "healthy", "service": "identity-engine", "version": "2.0.0"}


@app.post("/api/fingerprint", response_model=FingerprintResponse)
async def create_fingerprint(payload: FingerprintPayload):
    signals = {}
    risk_factors = []
    score = 50

    if payload.canvas_hash:
        signals["canvas_hash"] = {"weight": 15, "value": payload.canvas_hash[:16]}
        score += 15
    else:
        risk_factors.append("missing_canvas")

    if payload.webgl_hash:
        signals["webgl_hash"] = {"weight": 15, "value": payload.webgl_hash[:16]}
        score += 15
    else:
        risk_factors.append("missing_webgl")

    if payload.fonts_hash:
        signals["fonts_hash"] = {"weight": 10, "value": payload.fonts_hash[:16]}
        score += 10

    if payload.audio_hash:
        signals["audio_hash"] = {"weight": 10, "value": payload.audio_hash[:16]}
        score += 10

    if payload.screen_resolution:
        signals["screen_resolution"] = {"weight": 5, "value": payload.screen_resolution}
        score += 5
        if payload.color_depth:
            score += 2
    else:
        risk_factors.append("missing_screen_info")

    if payload.hardware_concurrency:
        signals["hardware_concurrency"] = {"weight": 4, "value": payload.hardware_concurrency}
        score += 4
    if payload.device_memory:
        signals["device_memory"] = {"weight": 4, "value": payload.device_memory}
        score += 4

    if payload.timezone:
        signals["timezone"] = {"weight": 5, "value": payload.timezone}
        score += 5

    if payload.platform and payload.os_name:
        signals["platform"] = {"weight": 5, "value": f"{payload.platform}/{payload.os_name}"}
        score += 5
    else:
        risk_factors.append("missing_platform_info")

    if payload.browser_name and payload.browser_version:
        signals["browser"] = {"weight": 3, "value": f"{payload.browser_name} {payload.browser_version}"}
        score += 3

    if payload.languages:
        signals["languages"] = {"weight": 2, "value": payload.languages}
        score += 2

    if payload.touch_support is not None:
        signals["touch_support"] = {"weight": 2, "value": payload.touch_support}
        score += 2

    if payload.visitor_id:
        signals["visitor_id"] = {"weight": 10, "value": payload.visitor_id}
        score += 10

    if payload.user_agent:
        ua_lower = payload.user_agent.lower()
        if "bot" in ua_lower or "crawl" in ua_lower or "spider" in ua_lower:
            score -= 30
            risk_factors.append("bot_user_agent")
        if "headless" in ua_lower:
            score -= 20
            risk_factors.append("headless_browser")

    score = max(0, min(100, score))

    fingerprint_hash = build_fingerprint_hash(payload)

    is_known, user_id = resolve_identity(fingerprint_hash, payload.mac)

    store_fingerprint(fingerprint_hash, payload, score, risk_factors)

    confidence = "high" if score >= 70 else "medium" if score >= 40 else "low"

    return FingerprintResponse(
        fingerprint_hash=fingerprint_hash,
        trust_score=score,
        signals_used=signals,
        risk_factors=risk_factors,
        is_known_device=is_known,
        user_id=user_id,
        confidence=confidence
    )


def build_fingerprint_hash(payload: FingerprintPayload) -> str:
    components = []

    if payload.visitor_id:
        components.append(payload.visitor_id)
    if payload.canvas_hash:
        components.append(payload.canvas_hash)
    if payload.webgl_hash:
        components.append(payload.webgl_hash)
    if payload.fonts_hash:
        components.append(payload.fonts_hash)
    if payload.audio_hash:
        components.append(payload.audio_hash)
    if payload.screen_resolution:
        components.append(payload.screen_resolution)
    if payload.timezone:
        components.append(payload.timezone)
    if payload.platform:
        components.append(payload.platform)
    if payload.user_agent:
        components.append(payload.user_agent)

    components.append(payload.ip)
    components.append(payload.nas_id)

    combined = "|".join(components)
    return hashlib.sha256(combined.encode()).hexdigest()


def resolve_identity(fingerprint_hash: str, mac: Optional[str]) -> tuple:
    try:
        conn = get_db_connection()
        cur = conn.cursor()

        cur.execute(
            "SELECT id, user_id FROM devices WHERE fingerprint_hash = %s LIMIT 1",
            (fingerprint_hash,)
        )

        result = cur.fetchone()

        if not result:
            prefix = "fp-" + fingerprint_hash[:16]
            cur.execute(
                "SELECT id, user_id FROM devices WHERE fingerprint_hash = %s LIMIT 1",
                (prefix,)
            )
            result = cur.fetchone()

        if result:
            device_id, user_id = result

            if mac:
                cur.execute(
                    "SELECT id FROM device_mac_histories WHERE device_id = %s AND mac_address = %s",
                    (device_id, mac)
                )

                if not cur.fetchone():
                    cur.execute(
                        "INSERT INTO device_mac_histories (device_id, mac_address, is_active, created_at) VALUES (%s, %s, %s, %s)",
                        (device_id, mac, True, datetime.utcnow())
                    )
                    conn.commit()

            cur.close()
            conn.close()
            return True, user_id

        cur.close()
        conn.close()
        return False, None

    except Exception as e:
        print(f"Error resolving identity: {e}")
        return False, None


def store_fingerprint(fingerprint_hash: str, payload: FingerprintPayload, score: int, risk_factors: list):
    try:
        conn = get_db_connection()
        cur = conn.cursor()

        risk_factors_json = Json(risk_factors)

        cur.execute("""
            INSERT INTO device_fingerprints
            (fingerprint_hash, visitor_id, ip_address, mac, nas_id, user_agent,
             platform, os_name, os_version, browser_name, browser_version,
             screen_resolution, color_depth, device_memory, hardware_concurrency,
             timezone, languages, touch_support, canvas_hash, webgl_hash,
             webgl_vendor, webgl_renderer, fonts_hash, audio_hash,
             trust_score, confidence, is_known_device, risk_factors, match_count)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 1)
            ON CONFLICT (fingerprint_hash) DO UPDATE SET
                trust_score = EXCLUDED.trust_score,
                confidence = EXCLUDED.confidence,
                is_known_device = EXCLUDED.is_known_device,
                risk_factors = EXCLUDED.risk_factors,
                match_count = device_fingerprints.match_count + 1,
                updated_at = NOW()
        """, (
            fingerprint_hash,
            payload.visitor_id,
            payload.ip,
            payload.mac,
            payload.nas_id,
            payload.user_agent,
            payload.platform,
            payload.os_name,
            payload.os_version,
            payload.browser_name,
            payload.browser_version,
            payload.screen_resolution,
            payload.color_depth,
            payload.device_memory,
            payload.hardware_concurrency,
            payload.timezone,
            payload.languages,
            payload.touch_support,
            payload.canvas_hash,
            payload.webgl_hash,
            payload.webgl_vendor,
            payload.webgl_renderer,
            payload.fonts_hash,
            payload.audio_hash,
            score,
            "high" if score >= 70 else "medium" if score >= 40 else "low",
            False,
            risk_factors_json,
        ))

        conn.commit()
        cur.close()
        conn.close()

    except Exception as e:
        print(f"Error storing fingerprint: {e}")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)