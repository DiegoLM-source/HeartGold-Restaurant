
import { Html5Qrcode } from 'html5-qrcode';

document.addEventListener('DOMContentLoaded', () => {

    const reader = document.getElementById('qr-reader');
    const startButton = document.getElementById('qr-start');
    const stopButton = document.getElementById('qr-stop');
    const tokenInput = document.getElementById('qr-token');
    const message = document.getElementById('qr-message');

    if (!reader || !startButton || !tokenInput || !message) {
        return;
    }

    const scanner = new Html5Qrcode('qr-reader');

    let scanning = false;

    function obtenerToken(contenido) {
        const valor = contenido.trim();

        try {
            const url = new URL(valor);

            const tokenParametro = url.searchParams.get('token');

            if (tokenParametro) {
                return tokenParametro;
            }

            const coincidencia = url.pathname.match(
                /\/cliente\/mesa\/(?:unirse\/)?([0-9a-f-]{36})\/?$/i
            );

            if (coincidencia) {
                return coincidencia[1];
            }
        } catch (error) {
            // El contenido puede ser un token y no una URL.
        }

        const tokenValido = /^[0-9a-f-]{36}$/i.test(valor);

        return tokenValido ? valor : null;
    }

    async function detenerEscaner() {
        if (!scanning) return;

        try {
            await scanner.stop();
        } catch (error) {
            console.error('No se pudo detener el escáner:', error);
        }

        scanning = false;
        startButton.hidden = false;
        stopButton.hidden = true;
    }

    async function iniciarEscaner() {
        try {
            message.textContent = 'Solicitando acceso a la cámara...';

            await scanner.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: {
                        width: 250,
                        height: 250
                    }
                },
                async (decodedText) => {

                    const token = obtenerToken(decodedText);

                    if (!token) {
                        message.textContent =
                            'El código escaneado no contiene un token válido.';
                        return;
                    }

                    tokenInput.value = token;

                    message.textContent =
                        'Código identificado. Validando sesión...';

                    await detenerEscaner();

                    tokenInput.form.requestSubmit();
                },
                () => {}
            );

            scanning = true;
            startButton.hidden = true;
            stopButton.hidden = false;

            message.textContent = 'Cámara activa. Escanea el código QR.';

        } catch (error) {
            console.error(error);

            message.textContent =
                'No se pudo activar la cámara. Verifica los permisos del navegador.';
        }
    }

    startButton.addEventListener('click', iniciarEscaner);
    stopButton.addEventListener('click', detenerEscaner);

});
