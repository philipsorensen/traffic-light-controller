#!/usr/bin/env python3
"""Turns off all lights and exits."""

import RPi.GPIO as GPIO
from config import PIN_RED, PIN_YELLOW, PIN_GREEN

GPIO.setmode(GPIO.BCM)
GPIO.setwarnings(False)

for pin in [PIN_RED, PIN_YELLOW, PIN_GREEN]:
    GPIO.setup(pin, GPIO.OUT)
    GPIO.output(pin, GPIO.HIGH)  # HIGH = OFF (active-low relay)

GPIO.cleanup()
print("All lights off.")
