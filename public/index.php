<?php
// ============================================================
// Traffic Light Controller — Control Panel
// ============================================================

// --- Configuration ------------------------------------------
(function () {
    $envFile = dirname(__DIR__) . '/.env';
    if (!file_exists($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
})();

define('PASSWORD',   $_ENV['PASSWORD']   ?? 'changeme');
define('STATE_FILE', dirname(__DIR__) . '/storage/state.json');

// --- Default state ------------------------------------------
$defaults = [
    'mode'     => 'off',
    'settings' => [
        'red_duration'        => 5,
        'red_yellow_duration' => 1,
        'green_duration'      => 5,
        'yellow_duration'     => 2,
        'flash_interval'      => 0.5,
        'all_flash_on_time'   => 0.5,
        'all_flash_off_time'  => 0.5,
    ],
];

// --- Helpers ------------------------------------------------
function read_state(array $defaults): array {
    if (!file_exists(STATE_FILE)) {
        return $defaults;
    }
    $data = json_decode(file_get_contents(STATE_FILE), true);
    return is_array($data) ? $data : $defaults;
}

function save_state(array $state): void {
    $dir = dirname(STATE_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
}

function is_authenticated(): bool {
    return !empty($_SESSION['auth']);
}

function redirect(string $location): void {
    header('Location: ' . $location);
    exit;
}

// --- Session ------------------------------------------------
session_start();

// --- API endpoint -------------------------------------------
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(read_state($defaults));
    exit;
}

// --- Handle POST --------------------------------------------
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Login
    if (isset($_POST['password'])) {
        if ($_POST['password'] === PASSWORD) {
            $_SESSION['auth'] = true;
            redirect('/?saved=1');
        } else {
            $error = 'Wrong password.';
        }
    }

    // Logout
    if (isset($_POST['logout'])) {
        session_destroy();
        redirect('/');
    }

    // Save mode
    if (isset($_POST['mode']) && is_authenticated()) {
        $allowed_modes = ['off', 'traffic_light', 'warning', 'all_flash', 'party'];
        $mode = in_array($_POST['mode'], $allowed_modes) ? $_POST['mode'] : 'off';

        $settings = [
            'red_duration'        => max(1, (int)($_POST['red_duration']        ?? 5)),
            'red_yellow_duration' => max(1, (int)($_POST['red_yellow_duration'] ?? 1)),
            'green_duration'      => max(1, (int)($_POST['green_duration']      ?? 5)),
            'yellow_duration'     => max(1, (int)($_POST['yellow_duration']     ?? 2)),
            'flash_interval'      => max(0.1, min(5.0, (float)($_POST['flash_interval']     ?? 0.5))),
            'all_flash_on_time'   => max(0.1, min(5.0, (float)($_POST['all_flash_on_time']  ?? 0.5))),
            'all_flash_off_time'  => max(0.1, min(5.0, (float)($_POST['all_flash_off_time'] ?? 0.5))),
        ];

        save_state(['mode' => $mode, 'settings' => $settings]);
        redirect('/?saved=1');
    }
}

// --- Load state for display ---------------------------------
$state    = read_state($defaults);
$mode     = $state['mode'];
$settings = $state['settings'];
$saved    = isset($_GET['saved']);

// ============================================================
// HTML
// ============================================================
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Light Controller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .light { transition: all 0.3s ease; }
        .light-on-red    { background: #ef4444; box-shadow: 0 0 24px 8px #ef444488; }
        .light-on-yellow { background: #facc15; box-shadow: 0 0 24px 8px #facc1588; }
        .light-on-green  { background: #22c55e; box-shadow: 0 0 24px 8px #22c55e88; }
        .light-off       { background: #1f2937; }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col items-center justify-center p-6">

    <div class="w-full max-w-lg space-y-8">

        <!-- Header -->
        <div class="text-center">
            <h1 class="text-3xl font-bold tracking-tight">Traffic Light</h1>
            <p class="text-gray-400 mt-1 text-sm">Control Panel</p>
        </div>

        <!-- Traffic Light Visual -->
        <div class="flex justify-center">
            <div class="bg-gray-800 rounded-3xl px-8 py-6 flex flex-col items-center gap-4 shadow-xl border border-gray-700">
                <?php
                $redClass    = ($mode !== 'off') ? 'light-on-red'    : 'light-off';
                $yellowClass = ($mode !== 'off') ? 'light-on-yellow' : 'light-off';
                $greenClass  = ($mode !== 'off' && $mode !== 'warning') ? 'light-on-green' : 'light-off';
                ?>
                <div class="light w-16 h-16 rounded-full <?= $redClass ?>"></div>
                <div class="light w-16 h-16 rounded-full <?= $yellowClass ?>"></div>
                <div class="light w-16 h-16 rounded-full <?= $greenClass ?>"></div>
            </div>
        </div>

        <?php if (!is_authenticated()): ?>
        <!-- Login Form -->
        <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800 shadow-lg">
            <h2 class="text-lg font-semibold mb-4">Sign in</h2>
            <?php if ($error): ?>
                <p class="text-red-400 text-sm mb-3"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <input
                    type="password"
                    name="password"
                    placeholder="Password"
                    autofocus
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-lg transition">
                    Sign in
                </button>
            </form>
        </div>

        <?php else: ?>
        <!-- Control Panel -->

        <?php if ($saved): ?>
        <div class="bg-green-900/50 border border-green-700 text-green-300 text-sm rounded-lg px-4 py-3">
            Settings saved. The Pi will pick up the change within a few seconds.
        </div>
        <?php endif; ?>

        <form method="post" class="bg-gray-900 rounded-2xl p-6 border border-gray-800 shadow-lg space-y-6">

            <!-- Mode Selector -->
            <div>
                <p class="text-sm font-medium text-gray-300 mb-3">Mode</p>
                <div class="grid grid-cols-2 gap-3">
                    <?php
                    $modes = [
                        'off'           => ['label' => 'Off',          'icon' => '⚫', 'desc' => 'All lights off'],
                        'traffic_light' => ['label' => 'Traffic Light', 'icon' => '🚦', 'desc' => 'Standard cycle'],
                        'warning'       => ['label' => 'Warning',       'icon' => '⚠️', 'desc' => 'Yellow flashing'],
                        'all_flash'     => ['label' => 'All Flash',     'icon' => '💡', 'desc' => 'All lights flashing'],
                        'party'         => ['label' => 'Party',         'icon' => '🎉', 'desc' => 'Random flashing'],
                    ];
                    foreach ($modes as $value => $info):
                        $checked = $mode === $value ? 'checked' : '';
                    ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="mode" value="<?= $value ?>" <?= $checked ?>
                            class="sr-only peer"
                            onchange="updateSettings()">
                        <div class="peer-checked:border-indigo-500 peer-checked:bg-indigo-950 border-2 border-gray-700 rounded-xl p-4 hover:border-gray-500 transition">
                            <div class="text-2xl mb-1"><?= $info['icon'] ?></div>
                            <div class="font-medium text-sm"><?= $info['label'] ?></div>
                            <div class="text-xs text-gray-400"><?= $info['desc'] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Settings: traffic light timing -->
            <div id="settings-traffic" class="space-y-3 <?= $mode !== 'traffic_light' ? 'hidden' : '' ?>">
                <p class="text-sm font-medium text-gray-300">Timing (seconds)</p>
                <?php
                $timing_fields = [
                    'red_duration'        => 'Red',
                    'red_yellow_duration' => 'Red + Yellow',
                    'green_duration'      => 'Green',
                    'yellow_duration'     => 'Yellow',
                ];
                foreach ($timing_fields as $key => $label):
                    $val = $settings[$key] ?? $defaults['settings'][$key];
                ?>
                <div class="flex items-center gap-4">
                    <label class="w-32 text-sm text-gray-400"><?= $label ?></label>
                    <input type="number" name="<?= $key ?>" value="<?= (int)$val ?>" min="1" max="120"
                        class="w-24 bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Settings: flash interval -->
            <div id="settings-flash" class="space-y-3 <?= !in_array($mode, ['warning', 'party']) ? 'hidden' : '' ?>">
                <p class="text-sm font-medium text-gray-300">Flash interval (seconds)</p>
                <input type="number" name="flash_interval"
                    value="<?= number_format((float)($settings['flash_interval'] ?? 0.5), 1) ?>"
                    min="0.1" max="5" step="0.1"
                    class="w-24 bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Settings: all flash on/off times -->
            <div id="settings-all-flash" class="space-y-3 <?= $mode !== 'all_flash' ? 'hidden' : '' ?>">
                <p class="text-sm font-medium text-gray-300">Flash timing (seconds)</p>
                <div class="flex items-center gap-4">
                    <label class="w-32 text-sm text-gray-400">On time</label>
                    <input type="number" name="all_flash_on_time"
                        value="<?= number_format((float)($settings['all_flash_on_time'] ?? 0.5), 1) ?>"
                        min="0.1" max="5" step="0.1"
                        class="w-24 bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex items-center gap-4">
                    <label class="w-32 text-sm text-gray-400">Off time</label>
                    <input type="number" name="all_flash_off_time"
                        value="<?= number_format((float)($settings['all_flash_off_time'] ?? 0.5), 1) ?>"
                        min="0.1" max="5" step="0.1"
                        class="w-24 bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-lg transition">
                    Save
                </button>
                <form method="post" class="inline">
                    <button type="submit" name="logout" value="1"
                        class="px-4 py-2.5 text-sm text-gray-400 hover:text-gray-200 border border-gray-700 rounded-lg transition">
                        Logout
                    </button>
                </form>
            </div>
        </form>
        <?php endif; ?>

        <!-- Footer -->
        <p class="text-center text-xs text-gray-600">
            Current mode: <span class="text-gray-400 font-medium"><?= htmlspecialchars($mode) ?></span>
            &nbsp;·&nbsp;
            <a href="/?api=1" class="hover:text-gray-300 underline" target="_blank">API</a>
        </p>

    </div>

    <script>
    function updateSettings() {
        const mode = document.querySelector('input[name="mode"]:checked')?.value;
        document.getElementById('settings-traffic').classList.toggle('hidden', mode !== 'traffic_light');
        document.getElementById('settings-flash').classList.toggle('hidden', !['warning', 'party'].includes(mode));
        document.getElementById('settings-all-flash').classList.toggle('hidden', mode !== 'all_flash');
    }
    </script>

</body>
</html>
