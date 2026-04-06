import os
from dotenv import load_dotenv

load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '..', '.env'))

API_URL       = os.getenv('API_URL', 'https://your-forge-app.com/?api=1')
POLL_INTERVAL = int(os.getenv('POLL_INTERVAL', 5))
PIN_RED       = int(os.getenv('PIN_RED',    11))
PIN_YELLOW    = int(os.getenv('PIN_YELLOW',  9))
PIN_GREEN     = int(os.getenv('PIN_GREEN',  10))
