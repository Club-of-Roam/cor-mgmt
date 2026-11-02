const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');

function uploader(success) {
    let ready, change, request;
    const makeNode = () => ({children: [], textContent: '', replaceChildren() {this.children = [];}, appendChild(child) {this.children.push(child);}});
    const hidden = makeNode(), send = makeNode(), status = makeNode();
    const input = {files: [{name: 'synthetic.png'}], addEventListener(type, callback) {change = callback;}};
    const form = {querySelector(selector) {return selector.includes('file') ? input : {value: selector.includes('nonce') ? 'synthetic-nonce' : '9'};}};
    hidden.closest = () => form;
    const document = {
        addEventListener(type, callback) {ready = callback;},
        getElementById(id) {return {image_hidden: hidden, image_send: send, image_ready: status, image_loading: makeNode()}[id];},
        createElement() {return {};}
    };
    class FormData {constructor() {this.fields = {};} append(key, value) {this.fields[key] = value;}}
    const fetch = async (url, options) => {
        request = {url, ...options};
        return {ok: success, json: async () => success ? {success: true, data: {url: 'uploads/synthetic.png'}} : {success: false, data: {message: 'Rejected'}}};
    };
    vm.runInNewContext(fs.readFileSync(path.join(root, 'js/app.js'), 'utf8'), {document, FormData, fetch, window: {corMgmtMedia: {url: '/wp-admin/admin-ajax.php'}}});
    ready();
    return {run: () => change(), hidden, send, status, request: () => request};
}

test('upload sends nonce and race to WordPress using same-origin credentials', async () => {
    const page = uploader(true);
    await page.run();
    assert.equal(page.request().url, '/wp-admin/admin-ajax.php');
    assert.equal(page.request().credentials, 'same-origin');
    assert.equal(page.request().method, 'POST');
    assert.equal(page.request().body.fields.cor_mgmt_nonce, 'synthetic-nonce');
    assert.equal(page.request().body.fields.race, '9');
    assert.equal(page.request().body.fields.action, 'cor_mgmt_upload_image');
    assert.equal(page.hidden.children[0].value, 'uploads/synthetic.png');
    assert.equal(page.send.children[0].type, 'submit');
});

test('rejected upload never enables sending an image', async () => {
    const page = uploader(false);
    await page.run();
    assert.equal(page.hidden.children.length, 0);
    assert.equal(page.send.children.length, 0);
    assert.equal(page.status.textContent, 'Rejected');
});

test('donation completion submits the nonce-protected server form', () => {
    let ready, click, submitted = 0;
    const document = {
        addEventListener(type, callback) {ready = callback;},
        getElementById(id) {
            if (id === 'donation-done-button') return {addEventListener(type, callback) {click = callback;}};
            if (id === 'donation-done-form') return {requestSubmit() {submitted++;}};
            return null;
        }
    };
    vm.runInNewContext(fs.readFileSync(path.join(root, 'js/h3-mgmt-sponsoring.js'), 'utf8'), {document, wp: {i18n: {_x: text => text}}});
    ready();
    click();
    assert.equal(submitted, 1);
});

test('canceling a destructive action prevents its POST submission', () => {
    let click, prevented = false;
    const document = {addEventListener(type, callback) {click = callback;}};
    const jQuery = () => ({ready() {}, click() {}, change() {}});
    vm.runInNewContext(fs.readFileSync(path.join(root, 'js/h3-mgmt-admin.js'), 'utf8'), {document, jQuery, window: {confirm: () => false}});
    click({target: {closest: () => ({dataset: {confirm: 'Really delete?'}})}, preventDefault() {prevented = true;}});
    assert.equal(prevented, true);
});
