const { app, BrowserWindow, dialog } = require('electron');
const { spawn } = require('child_process');
const http = require('http');

const HOST = '127.0.0.1';
const PORT = process.env.MEMO_APP_PORT || '32123';
const APP_URL = `http://${HOST}:${PORT}/auth.php`;
const PHP_COMMAND = process.env.PHP_EXECUTABLE || 'php';

let phpServer = null;
let startedPhpHere = false;

function isMemoAppRunning() {
    return new Promise((resolve) => {
        const request = http.get(APP_URL, (response) => {
            let body = '';

            response.setEncoding('utf8');
            response.on('data', (chunk) => {
                body += chunk;
            });
            response.on('end', () => {
                const looksLikeMemoApp = body.includes('Memo App') || body.includes('Login') || body.includes('Register');
                resolve(response.statusCode === 200 && looksLikeMemoApp);
            });
        });

        request.on('error', () => resolve(false));
        request.setTimeout(1000, () => {
            request.destroy();
            resolve(false);
        });
    });
}

function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForServer(retries = 20) {
    for (let attempt = 0; attempt < retries; attempt += 1) {
        if (await isMemoAppRunning()) {
            return true;
        }

        await wait(300);
    }

    return false;
}

async function ensurePhpServer() {
    if (await isMemoAppRunning()) {
        return;
    }

    phpServer = spawn(PHP_COMMAND, ['-S', `${HOST}:${PORT}`, '-t', __dirname], {
        cwd: __dirname,
        stdio: 'ignore',
        windowsHide: true,
    });

    startedPhpHere = true;

    phpServer.on('exit', () => {
        phpServer = null;
    });

    const ready = await waitForServer();
    if (!ready) {
        throw new Error('PHP server did not start. Make sure PHP is installed and available in PATH.');
    }
}

function createWindow() {
    const window = new BrowserWindow({
        width: 900,
        height: 720,
        minWidth: 640,
        minHeight: 520,
        autoHideMenuBar: true,
    });

    window.loadURL(APP_URL);
}

app.whenReady().then(async () => {
    try {
        await ensurePhpServer();
        createWindow();
    } catch (error) {
        dialog.showErrorBox('Cannot start Memo App', error.message);
        app.quit();
    }
});

app.on('window-all-closed', () => {
    if (startedPhpHere && phpServer) {
        phpServer.kill();
    }

    app.quit();
});
