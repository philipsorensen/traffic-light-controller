#!/usr/bin/env python3
"""
Light test script — randomly toggles lights one at a time.
Note: relay module is active-low (LOW = ON, HIGH = OFF).
Press Ctrl+C to exit.
"""

import time
import signal
import sys
import random
import RPi.GPIO as GPIO

from config import PIN_RED, PIN_YELLOW, PIN_GREEN

PINS = [
    (PIN_RED,    "Red"),
    (PIN_YELLOW, "Yellow"),
    (PIN_GREEN,  "Green"),
]

DELAY = 1.0


def all_off():
    for pin, _ in PINS:
        GPIO.output(pin, GPIO.HIGH)  # HIGH = OFF (active-low relay)


def cleanup(signum=None, frame=None):
    print("\nCleaning up GPIO...")
    all_off()
    GPIO.cleanup()
    sys.exit(0)


GPIO.setmode(GPIO.BCM)
GPIO.setwarnings(False)
for pin, _ in PINS:
    GPIO.setup(pin, GPIO.OUT)

all_off()

signal.signal(signal.SIGINT,  cleanup)
signal.signal(signal.SIGTERM, cleanup)

print("Testing lights — press Ctrl+C to stop.\n")

while True:
    pin, name = random.choice(PINS)
    print(f"  {name}")
    GPIO.output(pin, GPIO.LOW)   # LOW = ON
    time.sleep(DELAY)
    GPIO.output(pin, GPIO.HIGH)  # HIGH = OFF
    time.sleep(0.2)              # brief pause between lights
