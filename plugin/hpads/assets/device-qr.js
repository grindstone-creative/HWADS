(function () {
    'use strict';
    document.querySelectorAll('.hpads-device-qr').forEach(panel => {
        const status = panel.querySelector('.hpads-qr-status');
        const input = panel.querySelector('input[type="url"]');
        const url = panel.dataset.settingsUrl;
        panel.querySelector('.hpads-copy-device-url').addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(url);
                status.textContent = 'Settings link copied.';
            } catch (_) {
                input.focus(); input.select();
                status.textContent = 'Select and copy the settings link above.';
            }
        });
        try {
            if (!globalThis.HPADSQRCode) throw new Error('QR library unavailable');
            const qr = HPADSQRCode.QrCode.encodeText(url, HPADSQRCode.QrCode.Ecc.QUARTILE);
            const border = 4, size = qr.size + border * 2;
            const ns = 'http://www.w3.org/2000/svg';
            const svg = document.createElementNS(ns, 'svg');
            svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
            svg.setAttribute('width', '320'); svg.setAttribute('height', '320');
            svg.setAttribute('shape-rendering', 'crispEdges');
            const background = document.createElementNS(ns, 'rect');
            background.setAttribute('width', String(size)); background.setAttribute('height', String(size)); background.setAttribute('fill', '#fff');
            svg.append(background);
            const path = document.createElementNS(ns, 'path');
            const segments = [];
            for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) {
                if (qr.getModule(x,y)) segments.push('M' + (x + border) + ',' + (y + border) + 'h1v1h-1z');
            }
            path.setAttribute('d', segments.join(' ')); path.setAttribute('fill', '#000'); svg.append(path);
            panel.querySelector('.hpads-qr-image').replaceChildren(svg);
            const save = (blob, extension) => {
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a'); link.href = objectUrl;
                link.download = 'hpads-device-' + panel.dataset.deviceId + '-settings.' + extension;
                link.click(); setTimeout(() => URL.revokeObjectURL(objectUrl), 30000);
            };
            const svgButton = panel.querySelector('.hpads-download-device-svg');
            svgButton.disabled = false;
            svgButton.addEventListener('click', () => {
                save(new Blob([new XMLSerializer().serializeToString(svg)], {type:'image/svg+xml;charset=utf-8'}), 'svg');
            });
            const pngButton = panel.querySelector('.hpads-download-device-png');
            pngButton.disabled = false;
            pngButton.addEventListener('click', () => {
                const scale = 10, canvas = document.createElement('canvas');
                canvas.width = canvas.height = size * scale;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff'; ctx.fillRect(0,0,canvas.width,canvas.height);
                ctx.fillStyle = '#000';
                for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) {
                    if(qr.getModule(x,y)) ctx.fillRect((x+border)*scale,(y+border)*scale,scale,scale);
                }
                canvas.toBlob(blob => { if(blob) save(blob,'png'); else status.textContent='PNG export failed; use SVG.'; }, 'image/png');
            });
            status.textContent = 'QR ready. Login and device permissions are checked when opened.';
        } catch (_) {
            status.textContent = 'QR could not be generated. The settings link above still works.';
        }
    });
})();
