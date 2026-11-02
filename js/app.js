document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var hidden = document.getElementById('image_hidden');
    if (!hidden || !window.corMgmtMedia) return;
    var form = hidden.closest('form');
    var input = form && form.querySelector('input[type="file"]');
    if (!input) return;
    var loading = document.getElementById('image_loading');
    var ready = document.getElementById('image_ready');
    var send = document.getElementById('image_send');
    input.addEventListener('change', async function () {
        if (!input.files.length) return;
        if (loading) loading.textContent = 'Uploading…';
        if (ready) ready.textContent = '';
        hidden.replaceChildren();
        if (send) send.replaceChildren();
        try {
            var nonce = form.querySelector('[name="cor_mgmt_nonce"]');
            var race = form.querySelector('[name="cor_mgmt_race"]');
            if (!nonce || !race) throw new Error('Reload the form.');
            var body = new FormData();
            body.append('action', 'cor_mgmt_upload_image');
            body.append('cor_mgmt_nonce', nonce.value);
            body.append('race', race.value);
            body.append('photo', input.files[0]);
            var response = await fetch(window.corMgmtMedia.url, {method: 'POST', credentials: 'same-origin', body: body});
            var result = await response.json();
            if (!response.ok || !result.success) throw new Error((result.data && result.data.message) || 'Upload failed.');
            var field = document.createElement('input');
            field.type = 'hidden'; field.name = 'image_name'; field.value = result.data.url;
            hidden.appendChild(field);
            if (ready) ready.textContent = 'Upload complete. Send the picture with or without a message.';
            if (send) {
                var button = document.createElement('button');
                button.type = 'submit'; button.name = 'submit_form'; button.textContent = 'Send picture';
                send.appendChild(button);
            }
        } catch (error) {
            if (ready) ready.textContent = error.message;
        } finally { if (loading) loading.textContent = ''; }
    });
});
