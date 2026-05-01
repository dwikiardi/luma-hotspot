from pydantic import BaseModel, Field
from typing import Optional
from datetime import datetime


class FingerprintPayload(BaseModel):
    visitor_id: Optional[str] = None
    canvas_hash: Optional[str] = None
    screen: Optional[str] = None
    timezone: Optional[str] = None
    webgl_hash: Optional[str] = None
    fonts_hash: Optional[str] = None
    mac: Optional[str] = None
    ip: Optional[str] = "0.0.0.0"
    user_agent: str
    nas_id: str
    accept_lang: Optional[str] = None
    circuit_id: Optional[str] = None
    room_number: Optional[str] = None

    platform: Optional[str] = None
    os_name: Optional[str] = None
    os_version: Optional[str] = None
    browser_name: Optional[str] = None
    browser_version: Optional[str] = None
    device_type: Optional[str] = None
    screen_resolution: Optional[str] = None
    color_depth: Optional[int] = None
    device_memory: Optional[int] = None
    hardware_concurrency: Optional[int] = None
    languages: Optional[str] = None
    touch_support: Optional[bool] = None
    audio_hash: Optional[str] = None
    webgl_vendor: Optional[str] = None
    webgl_renderer: Optional[str] = None


class FingerprintResponse(BaseModel):
    fingerprint_hash: str
    trust_score: int
    signals_used: dict
    risk_factors: list
    is_known_device: bool
    user_id: Optional[int] = None
    confidence: str