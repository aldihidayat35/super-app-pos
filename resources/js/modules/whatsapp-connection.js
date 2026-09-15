import QRCode from 'qrcode';

export function initializeWhatsappConnection() {
    const root = document.querySelector('[data-whatsapp-connection]');
    if (!root) return;

    const statusUrl = root.dataset.statusUrl;
    const canvas = root.querySelector('[data-wa-qr]');
    const qrBox = root.querySelector('[data-wa-qr-box]');
    const statusLabel = root.querySelector('[data-wa-status]');
    const phoneLabel = root.querySelector('[data-wa-phone]');
    const nameLabel = root.querySelector('[data-wa-name]');
    const errorBox = root.querySelector('[data-wa-error]');
    const messageHistory = root.querySelector('[data-wa-message-history]');
    const primaryAction = root.querySelector('[data-wa-primary-action]');
    const primaryLabel = root.querySelector('[data-wa-primary-label]');
    let pollTimer = null;

    const request = (input, options = {}) => {
        if (typeof window.appFetch === 'function') return window.appFetch(input, options);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        return fetch(input, {
            ...options,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...options.headers,
            },
        });
    };

    const renderMessages = (messages = []) => {
        if (!messageHistory) return;
        messageHistory.replaceChildren();
        if (!messages.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 9;
            cell.className = 'text-center text-muted py-8';
            cell.textContent = 'Belum ada pesan WhatsApp yang dikirim.';
            row.appendChild(cell);
            messageHistory.appendChild(row);
            return;
        }

        const badgeClasses = {
            queued: 'badge-light-warning', retry: 'badge-light-info', sent: 'badge-light-success',
            failed: 'badge-light-danger', skipped: 'badge-light-secondary',
        };
        messages.forEach((message) => {
            const row = document.createElement('tr');
            const timeCell = document.createElement('td');
            timeCell.textContent = message.created_at || '-';
            row.appendChild(timeCell);

            const typeCell = document.createElement('td');
            const typeBadge = document.createElement('span');
            typeBadge.className = 'badge badge-light-primary';
            typeBadge.textContent = message.type || 'Pesan Manual';
            typeCell.appendChild(typeBadge);
            row.appendChild(typeCell);

            const recipientCell = document.createElement('td');
            const recipientName = document.createElement('div');
            recipientName.className = 'fw-semibold';
            recipientName.textContent = message.recipient_name || 'Nomor eksternal';
            recipientCell.appendChild(recipientName);
            if (message.recipient_linked) {
                const linkedBadge = document.createElement('span');
                linkedBadge.className = 'badge badge-light-primary mt-1';
                linkedBadge.textContent = 'Akun sistem';
                recipientCell.appendChild(linkedBadge);
            }
            row.appendChild(recipientCell);

            [message.destination || '-', message.message || '-'].forEach((value) => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });

            const statusCell = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = `badge ${badgeClasses[message.status] || 'badge-light-secondary'}`;
            badge.textContent = message.status_label || message.status;
            statusCell.appendChild(badge);
            if (message.sent_at) {
                const sentAt = document.createElement('div');
                sentAt.className = 'text-muted fs-8 mt-1';
                sentAt.textContent = message.sent_at;
                statusCell.appendChild(sentAt);
            }
            row.appendChild(statusCell);

            const attemptCell = document.createElement('td');
            attemptCell.textContent = String(message.attempts ?? 0);
            row.appendChild(attemptCell);
            const detailCell = document.createElement('td');
            detailCell.className = message.error ? 'text-danger' : 'text-muted';
            detailCell.textContent = message.error || message.provider_message_id || '-';
            row.appendChild(detailCell);

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            const detailLink = document.createElement('a');
            detailLink.className = 'btn btn-sm btn-light-primary';
            detailLink.href = message.detail_url;
            detailLink.textContent = 'Detail Pesan';
            actionCell.appendChild(detailLink);
            row.appendChild(actionCell);
            messageHistory.appendChild(row);
        });
    };

    const render = async (payload) => {
        root.dataset.status = payload.status;
        statusLabel.textContent = ({ connected: 'Terhubung', connecting: 'Menghubungkan', qr_ready: 'Menunggu pemindaian QR', reconnecting: 'Menyambungkan ulang', logged_out: 'Telah diputus', disconnected: 'Tidak terhubung' })[payload.status] || payload.status;
        phoneLabel.textContent = payload.phone || '-';
        nameLabel.textContent = payload.name || '-';
        errorBox.textContent = payload.last_error || '';
        errorBox.classList.toggle('d-none', !payload.last_error);

        if (primaryAction && primaryLabel) {
            const connected = payload.status === 'connected';
            primaryAction.dataset.waAction = connected ? 'disconnect' : 'connect';
            primaryAction.dataset.url = connected ? root.dataset.disconnectUrl : root.dataset.connectUrl;
            primaryAction.classList.toggle('btn-success', !connected);
            primaryAction.classList.toggle('btn-light-danger', connected);
            primaryLabel.textContent = connected ? 'Putuskan WhatsApp' : 'Hubungkan WhatsApp';
            const icon = primaryAction.querySelector('i');
            icon?.classList.toggle('ki-whatsapp', !connected);
            icon?.classList.toggle('ki-disconnect', connected);
        }

        qrBox.classList.toggle('d-none', !payload.qr);
        if (payload.qr && canvas) {
            try {
                await QRCode.toCanvas(canvas, payload.qr, { width: 280, margin: 2, errorCorrectionLevel: 'M' });
            } catch (_) {
                qrBox.classList.add('d-none');
                errorBox.textContent = 'Kode QR tidak dapat ditampilkan. Silakan minta QR baru.';
                errorBox.classList.remove('d-none');
            }
        }
        root.querySelectorAll('[data-show-status]').forEach((element) => {
            element.classList.toggle('d-none', !element.dataset.showStatus.split(',').includes(payload.status));
        });
        renderMessages(payload.messages);
        const hasPendingMessages = (payload.messages || []).some((message) => ['queued', 'retry'].includes(message.status));
        const shouldPoll = ['connecting', 'qr_ready', 'reconnecting'].includes(payload.status) || hasPendingMessages;
        if (shouldPoll && !pollTimer) pollTimer = window.setInterval(refresh, 2500);
        if (!shouldPoll && pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    const refresh = async () => {
        try {
            const response = await request(statusUrl);
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Status gateway tidak dapat dimuat.');
            await render(payload);
        } catch (_) {
            errorBox.textContent = 'Status gateway belum dapat diperbarui.';
            errorBox.classList.remove('d-none');
        }
    };

    root.querySelectorAll('[data-wa-action]').forEach((button) => button.addEventListener('click', async () => {
        const isDisconnect = button.dataset.waAction === 'disconnect';
        if (isDisconnect && window.Swal) {
            const answer = await window.Swal.fire({ title: 'Putuskan WhatsApp?', text: 'QR baru harus dipindai untuk menghubungkannya kembali.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, putuskan', cancelButtonText: 'Batal' });
            if (!answer.isConfirmed) return;
        }
        button.disabled = true;
        try {
            const response = await request(button.dataset.url, { method: isDisconnect ? 'DELETE' : 'POST' });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Permintaan gagal.');
            await render(payload);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
        } finally {
            button.disabled = false;
        }
    }));

    render(JSON.parse(root.dataset.initial)).catch(() => {
        errorBox.textContent = 'Tampilan koneksi WhatsApp tidak dapat dimuat. Silakan muat ulang halaman.';
        errorBox.classList.remove('d-none');
    });
}
