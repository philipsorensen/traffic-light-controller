# Traffic Light Controller

Control a 3-light relay module (red, yellow, green) connected to a Raspberry Pi via a simple web interface.

## How it works

1. The **PHP app** (hosted on Laravel Forge) serves a control panel and a JSON API endpoint.
2. The **Python script** (running on the Raspberry Pi) polls the API and drives the GPIO pins accordingly.

## Modes

| Mode | Description |
|---|---|
| Off | All lights off |
| Traffic Light | Standard red → red+yellow → green → yellow cycle |
| Warning | Yellow flashes slowly |
| Party | All lights flash in a random pattern |

---

## Setup

### 1. Environment

Copy the example env file and fill in your values:

```bash
cp .env.example .env
```

| Variable | Description |
|---|---|
| `PASSWORD` | Password for the web control panel |
| `API_URL` | Full URL to the PHP app, e.g. `https://your-app.com/?api=1` |
| `POLL_INTERVAL` | How often the Pi polls the API (seconds) |
| `PIN_RED` | BCM GPIO pin for the red light |
| `PIN_YELLOW` | BCM GPIO pin for the yellow light |
| `PIN_GREEN` | BCM GPIO pin for the green light |

---

### 2. PHP App (Laravel Forge)

- Create a new **Basic PHP** site on Forge and point it at this repository.
- The web directory should be set to `/public` (Forge's default).
- Place the `.env` file in the repo root on the server (one level above `public/`).
- PHP will automatically create `storage/state.json` on the first save.

No build step or composer install required.

---

### 3. Raspberry Pi

Install dependencies:

```bash
pip3 install -r raspberry-pi/requirements.txt
```

Copy the systemd service file and enable it:

```bash
sudo cp raspberry-pi/traffic_light.service /etc/systemd/system/
sudo systemctl enable --now traffic_light
```

View logs:

```bash
journalctl -u traffic_light -f
```

The script reads the shared `.env` from the repo root. If you cloned the repo to `/home/pi/traffic-light-controller/`, no path changes are needed.

---

## Repository Structure

```
.
├── .env.example                  # Copy to .env and fill in values
├── public/
│   └── index.php                 # PHP control panel + API
├── raspberry-pi/
│   ├── config.py                 # Loads settings from .env
│   ├── traffic_light.py          # Main controller loop
│   ├── traffic_light.service     # systemd unit file
│   └── requirements.txt
└── storage/
    └── state.json                # Written at runtime, gitignored
```

## API

The Pi polls `GET /?api=1`. No authentication required. Example response:

```json
{
    "mode": "traffic_light",
    "settings": {
        "red_duration": 5,
        "red_yellow_duration": 1,
        "green_duration": 5,
        "yellow_duration": 2,
        "flash_interval": 0.5
    }
}
```
