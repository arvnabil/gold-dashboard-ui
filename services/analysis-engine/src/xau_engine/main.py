from fastapi import FastAPI, HTTPException, Header, Depends
from pydantic import BaseModel
from typing import List, Optional
import os

app = FastAPI(
    title="XAUUSD Technical Analysis Engine",
    version="0.1.0",
    description="Deterministic calculation engine for SMC, SNR, SND, and setup states"
)

INTERNAL_TOKEN = os.getenv("ENGINE_INTERNAL_TOKEN", "engine_dev_token")

def verify_token(authorization: Optional[str] = Header(None)):
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Unauthorized")
    token = authorization.split(" ")[1]
    if token != INTERNAL_TOKEN:
        raise HTTPException(status_code=403, detail="Forbidden")

@app.get("/health")
def health_check():
    return {
        "status": "HEALTHY",
        "service": "analysis-engine",
        "version": "0.1.0"
    }

class CandleInput(BaseModel):
    opened_at: str
    closed_at: str
    open: str
    high: str
    low: str
    close: str
    tick_volume: int
    spread: int
    is_closed: bool

class SnapshotEvaluationRequest(BaseModel):
    feed_id: str
    timeframe: str
    as_of: str
    candles: List[CandleInput]

@app.post("/internal/v1/evaluate", dependencies=[Depends(verify_token)])
def evaluate_snapshot(request: SnapshotEvaluationRequest):
    return {
        "feed_id": request.feed_id,
        "timeframe": request.timeframe,
        "as_of": request.as_of,
        "bias": "BULLISH",
        "structure_events": [],
        "zones": [],
        "setups": []
    }
