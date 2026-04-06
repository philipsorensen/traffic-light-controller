#!/usr/bin/env python3
"""
Traffic Light Controller
Polls the PHP control server and drives GPIO relay pins accordingly.
"""

import signal
import sys
import time
import random
import logging
import requests
import RPi.GPIO as GPIO

from config import API_URL, POLL_INTERVAL, PIN_RED, PIN_YELLOW, PIN_GREEN

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
log = logging.getLogger(__name__)

PINS = [PIN_RED, PIN_YELLOW, PIN_GREEN]


# ---------------------------------------------------------------------------
# GPIO setup / teardown
# ---------------------------------------------------------------------------

def setup_gpio():
    GPIO.setmode(GPIO.BCM)
    GPIO.setwarnings(False)
    for pin in PINS:
        GPIO.setup(pin, GPIO.OUT)
        GPIO.output(pin, GPIO.HIGH)  # HIGH = OFF (active-low relay)


def cleanup(signum=None, frame=None):
    log.info("Shutting down — cleaning up GPIO")
    for pin in PINS:
        GPIO.output(pin, GPIO.HIGH)  # HIGH = OFF (active-low relay)
    GPIO.cleanup()
    sys.exit(0)


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def set_lights(red=False, yellow=False, green=False):
    # Active-low relay: LOW = ON, HIGH = OFF
    GPIO.output(PIN_RED,    GPIO.LOW if red    else GPIO.HIGH)
    GPIO.output(PIN_YELLOW, GPIO.LOW if yellow else GPIO.HIGH)
    GPIO.output(PIN_GREEN,  GPIO.LOW if green  else GPIO.HIGH)


def fetch_state():
    try:
        resp = requests.get(API_URL, timeout=5)
        resp.raise_for_status()
        return resp.json()
    except Exception as exc:
        log.error("Failed to fetch state: %s", exc)
        return None


# ---------------------------------------------------------------------------
# Mode handlers
# Each handler runs one "tick" of its pattern, then returns so the main loop
# can re-poll.  To keep lights responsive, handlers sleep in short chunks and
# check elapsed time rather than blocking for the full duration.
# ---------------------------------------------------------------------------

def mode_off():
    set_lights()
    time.sleep(POLL_INTERVAL)


# Traffic light phase order: red → red+yellow → green → yellow → red …
_TL_PHASES = ["red", "red_yellow", "green", "yellow"]
_tl_phase_index = 0
_tl_phase_start = 0.0


def _tl_phase_duration(phase, s):
    return {
        "red":       s.get("red_duration", 5),
        "red_yellow": s.get("red_yellow_duration", 1),
        "green":     s.get("green_duration", 5),
        "yellow":    s.get("yellow_duration", 2),
    }[phase]


def _tl_set_lights(phase):
    set_lights(
        red    = phase in ("red", "red_yellow"),
        yellow = phase in ("red_yellow", "yellow"),
        green  = phase == "green",
    )


def mode_traffic_light(settings):
    global _tl_phase_index, _tl_phase_start

    now = time.time()
    phase = _TL_PHASES[_tl_phase_index]
    duration = _tl_phase_duration(phase, settings)

    if _tl_phase_start == 0.0:
        # First run — start at red
        _tl_phase_start = now

    _tl_set_lights(phase)

    elapsed = now - _tl_phase_start
    remaining = duration - elapsed

    if remaining <= 0:
        # Advance to next phase
        _tl_phase_index = (_tl_phase_index + 1) % len(_TL_PHASES)
        _tl_phase_start = now
        phase = _TL_PHASES[_tl_phase_index]
        _tl_set_lights(phase)
        log.info("Traffic light → %s", phase)
        # Sleep until next poll or phase end, whichever comes first
        next_duration = _tl_phase_duration(phase, settings)
        time.sleep(min(POLL_INTERVAL, next_duration))
    else:
        time.sleep(min(POLL_INTERVAL, remaining))


def mode_warning(settings):
    """Yellow flashes slowly."""
    interval = settings.get("flash_interval", 0.5)
    set_lights(yellow=True)
    time.sleep(interval)
    set_lights()
    time.sleep(interval)


def mode_party(settings):
    """All lights flash in a rapid random pattern."""
    interval = settings.get("flash_interval", 0.5)
    patterns = [
        (True,  False, False),
        (False, True,  False),
        (False, False, True),
        (True,  True,  False),
        (False, True,  True),
        (True,  False, True),
        (True,  True,  True),
    ]
    r, y, g = random.choice(patterns)
    set_lights(red=r, yellow=y, green=g)
    time.sleep(interval)


# ---------------------------------------------------------------------------
# Main loop
# ---------------------------------------------------------------------------

_MODE_HANDLERS = {
    "off":           lambda s: mode_off(),
    "traffic_light": mode_traffic_light,
    "warning":       mode_warning,
    "party":         mode_party,
}

_current_mode = None


def main():
    global _current_mode, _tl_phase_index, _tl_phase_start

    setup_gpio()
    signal.signal(signal.SIGINT,  cleanup)
    signal.signal(signal.SIGTERM, cleanup)

    log.info("Traffic light controller started. Polling %s every %ss", API_URL, POLL_INTERVAL)

    while True:
        state = fetch_state()

        if state is None:
            # API unreachable — keep current state, wait before retry
            time.sleep(POLL_INTERVAL)
            continue

        mode     = state.get("mode", "off")
        settings = state.get("settings", {})

        if mode != _current_mode:
            log.info("Mode changed: %s → %s", _current_mode, mode)
            _current_mode = mode
            # Reset traffic light state machine on mode change
            _tl_phase_index = 0
            _tl_phase_start = 0.0

        handler = _MODE_HANDLERS.get(mode)
        if handler:
            handler(settings)
        else:
            log.warning("Unknown mode '%s' — defaulting to off", mode)
            mode_off()


if __name__ == "__main__":
    main()
