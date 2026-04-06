#!/usr/bin/env python3
"""
Light test script — cycles through each light one at a time.
Run this to verify GPIO wiring before starting the main controller.
Press Ctrl+C to exit.
"""

import time
import signal
import sys
import RPi.GPIO as GPIO

from config import PIN_RED, PIN_YELLOW, PIN_GREEN

PINS = [
    (PIN_RED,    "Red"),
    (PIN_YELLOW, "Yellow"),
    (PIN_GREEN,  "Green"),
]

DELAY = 1.5  # seconds each light stays on


def cleanup(signum=None, frame=None):
    print("\nCleaning up GPIO...")
    for pin, _ in PINS:
        GPIO.output(pin, GPIO.LOW)
    GPIO.cleanup()
    sys.exit(0)


GPIO.setmode(GPIO.BCM)
GPIO.setwarnings(False)
for pin, _ in PINS:
    GPIO.setup(pin, GPIO.OUT)
    GPIO.output(pin, GPIO.LOW)

signal.signal(signal.SIGINT,  cleanup)
signal.signal(signal.SIGTERM, cleanup)

print("Testing lights — press Ctrl+C to stop.\n")

while True:
    for pin, name in PINS:
        print(f"  {name}")
        GPIO.output(pin, GPIO.HIGH)
        time.sleep(DELAY)
        GPIO.output(pin, GPIO.LOW)
