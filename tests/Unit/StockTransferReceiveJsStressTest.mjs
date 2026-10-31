import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert';

// 1. Read the Blade template to ensure we are testing the EXACT script
const bladePath = path.resolve('resources/views/retail/stock-transfers/receive.blade.php');
const bladeContent = fs.readFileSync(bladePath, 'utf8');

// Extract the script between <script> and </script>
const scriptMatch = bladeContent.match(/<script>([\s\S]*?)<\/script>/);
if (!scriptMatch) {
    throw new Error('Could not find <script> in receive.blade.php');
}
const rawScript = scriptMatch[1];

console.log('[HARNESS] Successfully extracted JavaScript from receive.blade.php');
console.log(`[HARNESS] Script size: ${rawScript.length} characters`);

// 2. Setup a faithful DOM and jQuery mock environment for headless execution
class ClassList {
    constructor() {
        this.classes = new Set();
    }
    add(...names) {
        names.forEach(n => this.classes.add(n));
    }
    remove(...names) {
        names.forEach(n => this.classes.delete(n));
    }
    contains(name) {
        return this.classes.has(name);
    }
    toString() {
        return Array.from(this.classes).join(' ');
    }
}

class MockElement {
    constructor(tagName, id = '', className = '') {
        this.tagName = tagName.toUpperCase();
        this.id = id;
        this.classList = new ClassList();
        if (className) {
            className.split(/\s+/).filter(Boolean).forEach(c => this.classList.add(c));
        }
        this.attributes = new Map();
        this.style = {};
        this.value = '';
        this.children = [];
        this.parentElement = null;
        this.innerHTML = '';
        this.textContent = '';
        this.disabled = false;
    }

    setAttribute(name, val) {
        this.attributes.set(name, String(val));
    }

    getAttribute(name) {
        return this.attributes.has(name) ? this.attributes.get(name) : null;
    }

    appendChild(child) {
        child.parentElement = this;
        this.children.push(child);
        return child;
    }

    closest(selector) {
        let cur = this;
        while (cur) {
            if (cur.matches(selector)) return cur;
            cur = cur.parentElement;
        }
        return null;
    }

    matches(selector) {
        if (selector.startsWith('.')) {
            return this.classList.contains(selector.slice(1));
        }
        if (selector.startsWith('#')) {
            return this.id === selector.slice(1);
        }
        return this.tagName.toLowerCase() === selector.toLowerCase();
    }

    querySelector(selector) {
        for (const child of this.children) {
            if (child.matches(selector)) return child;
            const res = child.querySelector(selector);
            if (res) return res;
        }
        return null;
    }

    querySelectorAll(selector) {
        let results = [];
        for (const child of this.children) {
            if (child.matches(selector)) results.push(child);
            results = results.concat(child.querySelectorAll(selector));
        }
        return results;
    }
}

function createDOM() {
    const documentRoot = new MockElement('document');

    const totalInTransitEl = new MockElement('div', 'summary-total-in-transit');
    const totalReceivedEl = new MockElement('div', 'summary-total-received');
    const totalDamagedEl = new MockElement('div', 'summary-total-damaged');
    const totalDiscrepancyEl = new MockElement('div', 'summary-total-discrepancy');
    const alertContainerEl = new MockElement('div', 'summary-status-alert');
    const submitBtnEl = new MockElement('button', 'btn-submit-receive');
    const receiveAllBtn = new MockElement('button', 'btn-receive-all', 'btn btn-receive-all-action');

    documentRoot.appendChild(totalInTransitEl);
    documentRoot.appendChild(totalReceivedEl);
    documentRoot.appendChild(totalDamagedEl);
    documentRoot.appendChild(totalDiscrepancyEl);
    documentRoot.appendChild(alertContainerEl);
    documentRoot.appendChild(submitBtnEl);
    documentRoot.appendChild(receiveAllBtn);

    const eventListeners = new Map();

    const documentMock = {
        querySelectorAll: (sel) => documentRoot.querySelectorAll(sel),
        querySelector: (sel) => documentRoot.querySelector(sel),
        addEventListener: (event, handler) => {
            if (!eventListeners.has(event)) eventListeners.set(event, []);
            eventListeners.get(event).push(handler);
        }
    };

    function addRow(itemId, inTransitQty, initialReceived = null, initialDamaged = 0, initialDiscrepancy = 0) {
        const row = new MockElement('tr', '', 'stock-transfer-item-row');
        row.setAttribute('data-item-id', String(itemId));
        row.setAttribute('data-in-transit', String(inTransitQty));

        const receivedInput = new MockElement('input', '', 'form-control form-control-sm receive-qty-input quantity-received');
        receivedInput.value = initialReceived !== null ? String(initialReceived) : String(inTransitQty);

        const msgEl = new MockElement('div', '', 'text-danger fs-8 fw-bold mt-1 d-none row-exceeded-msg');

        const damagedInput = new MockElement('input', '', 'form-control form-control-sm receive-qty-input quantity-damaged');
        damagedInput.value = String(initialDamaged);

        const discrepancyInput = new MockElement('input', '', 'form-control form-control-sm receive-qty-input quantity-discrepancy');
        discrepancyInput.value = String(initialDiscrepancy);

        row.appendChild(receivedInput);
        row.appendChild(msgEl);
        row.appendChild(damagedInput);
        row.appendChild(discrepancyInput);

        documentRoot.appendChild(row);
        return { row, receivedInput, msgEl, damagedInput, discrepancyInput };
    }

    // jQuery mock
    const delegatedHandlers = [];
    const readyCallbacks = [];

    function $(arg) {
        if (typeof arg === 'function') {
            readyCallbacks.push(arg);
            return;
        }
        if (arg === documentMock || arg === 'document' || arg === documentRoot) {
            return {
                on: (eventNames, selector, handler) => {
                    const events = eventNames.split(/\s+/).filter(Boolean);
                    for (const ev of events) {
                        delegatedHandlers.push({ event: ev, selector, handler });
                    }
                }
            };
        }

        const selector = arg;
        const el = documentRoot.querySelector(selector);
        return {
            text: (val) => {
                if (val !== undefined && el) el.textContent = String(val);
                return el ? el.textContent : '';
            },
            html: (val) => {
                if (val !== undefined && el) el.innerHTML = String(val);
                return el ? el.innerHTML : '';
            },
            prop: (name, val) => {
                if (val !== undefined && el) el[name] = Boolean(val);
                return el ? el[name] : undefined;
            }
        };
    }

    $.on = (ev, sel, fn) => $(documentMock).on(ev, sel, fn);

    function triggerEvent(element, eventType) {
        for (const dh of delegatedHandlers) {
            if (dh.event === eventType) {
                if (element.matches(dh.selector)) {
                    dh.handler.call(element, {
                        preventDefault: () => {},
                        target: element,
                        currentTarget: element
                    });
                }
            }
        }
    }

    return {
        documentMock,
        documentRoot,
        $,
        readyCallbacks,
        triggerEvent,
        addRow,
        elements: {
            totalInTransitEl,
            totalReceivedEl,
            totalDamagedEl,
            totalDiscrepancyEl,
            alertContainerEl,
            submitBtnEl,
            receiveAllBtn
        }
    };
}

// 3. Run the Stress Scenarios
console.log('\n--- STARTING EMPIRICAL CLIENT-SIDE STRESS SUITE ---');

const env = createDOM();
const { documentMock, $, readyCallbacks, triggerEvent, addRow, elements } = env;

// Add 1 test item: inTransit = 5
const item1 = addRow(101, 5, 5, 0, 0);

// Execute the extracted script inside the mock environment
const fn = new Function('$', 'document', rawScript);
fn($, documentMock);

// Trigger DOM ready
readyCallbacks.forEach(cb => cb());

// Scenario 1: Initial Load Verification
console.log('\n[TEST 1] Initial Load State Verification:');
assert.strictEqual(elements.totalInTransitEl.textContent, '5', 'Initial total in-transit must be 5');
assert.strictEqual(elements.totalReceivedEl.textContent, '5', 'Initial total received must be 5');
assert.strictEqual(elements.totalDamagedEl.textContent, '0', 'Initial total damaged must be 0');
assert.strictEqual(elements.totalDiscrepancyEl.textContent, '0', 'Initial total discrepancy must be 0');
assert.ok(elements.alertContainerEl.innerHTML.includes('Penerimaan Sempurna'), 'Alert state must be Green (Penerimaan Sempurna)');
assert.strictEqual(elements.submitBtnEl.disabled, false, 'Submit button must be enabled initially');
console.log('-> PASS: Initial load is Green, submit button enabled, totals correct.');

// Scenario 2: Simulate typing quantity_damaged = 2 on inTransit = 5
console.log('\n[TEST 2] Typing quantity_damaged = 2:');
item1.damagedInput.value = '2';
triggerEvent(item1.damagedInput, 'input');

assert.strictEqual(item1.receivedInput.value, '3', 'quantity_received must auto-update to 3 (5 - 2 = 3)');
assert.strictEqual(elements.totalReceivedEl.textContent, '3', 'Summary total received must be 3');
assert.strictEqual(elements.totalDamagedEl.textContent, '2', 'Summary total damaged must be 2');
assert.ok(elements.alertContainerEl.innerHTML.includes('Catatan Pengiriman'), 'Alert state must switch to Yellow (Catatan Pengiriman)');
assert.strictEqual(elements.submitBtnEl.disabled, false, 'Submit button must remain enabled for valid damaged entry');
assert.ok(!item1.row.classList.contains('table-danger'), 'Row should NOT have table-danger');
assert.ok(!item1.receivedInput.classList.contains('is-invalid'), 'Input should NOT have is-invalid');
assert.ok(item1.msgEl.classList.contains('d-none'), 'Exceeded message must remain hidden (d-none)');
console.log('-> PASS: quantity_received updated to 3, Yellow alert active, submit enabled.');

// Scenario 3: Simulate typing quantity_damaged = 6 on inTransit = 5
console.log('\n[TEST 3] Typing quantity_damaged = 6 (Exceeded inTransit):');
item1.damagedInput.value = '6';
triggerEvent(item1.damagedInput, 'input');

assert.strictEqual(item1.receivedInput.value, '0', 'quantity_received clamped at 0 (Math.max(0, 5 - 6) = 0)');
assert.strictEqual(elements.totalDamagedEl.textContent, '6', 'Summary total damaged must be 6');
assert.ok(item1.row.classList.contains('table-danger'), 'Row MUST have table-danger class');
assert.strictEqual(item1.row.style.outline, '2px solid var(--bs-danger, #f1416c)', 'Row MUST have 2px red outline');
assert.ok(item1.receivedInput.classList.contains('is-invalid'), 'Input MUST have is-invalid class');
assert.ok(!item1.msgEl.classList.contains('d-none'), 'Exceeded message MUST be visible (d-none removed)');
assert.ok(elements.alertContainerEl.innerHTML.includes('Kuantitas Tidak Valid'), 'Alert state MUST switch to Red (Kuantitas Tidak Valid)');
assert.strictEqual(elements.submitBtnEl.disabled, true, 'Submit button MUST be disabled');
console.log('-> PASS: Exceeded error styling triggered, Red alert active, submit button disabled.');

// Scenario 4: Simulate clicking #btn-receive-all
console.log('\n[TEST 4] Clicking #btn-receive-all:');
triggerEvent(elements.receiveAllBtn, 'click');

assert.strictEqual(item1.receivedInput.value, '5', 'quantity_received must reset to inTransit (5)');
assert.strictEqual(item1.damagedInput.value, '0', 'quantity_damaged must reset to 0');
assert.strictEqual(item1.discrepancyInput.value, '0', 'quantity_discrepancy must reset to 0');
assert.strictEqual(elements.totalReceivedEl.textContent, '5', 'Summary total received must be 5');
assert.strictEqual(elements.totalDamagedEl.textContent, '0', 'Summary total damaged must be 0');
assert.strictEqual(elements.totalDiscrepancyEl.textContent, '0', 'Summary total discrepancy must be 0');
assert.ok(!item1.row.classList.contains('table-danger'), 'table-danger must be removed');
assert.strictEqual(item1.row.style.outline, '', 'Outline must be cleared');
assert.ok(!item1.receivedInput.classList.contains('is-invalid'), 'is-invalid must be removed');
assert.ok(item1.msgEl.classList.contains('d-none'), 'Exceeded message must be hidden');
assert.ok(elements.alertContainerEl.innerHTML.includes('Penerimaan Sempurna'), 'Alert state must return to Green');
assert.strictEqual(elements.submitBtnEl.disabled, false, 'Submit button must be re-enabled');
console.log('-> PASS: 1-click receive reset all values, Green alert restored, submit re-enabled.');

// Scenario 5: Multi-item & Decimal Precision Stress Test
console.log('\n[TEST 5] Multi-item with Decimals (Item 1: 5.0, Item 2: 12.5):');
const item2 = addRow(102, 12.5, 12.5, 0, 0);

// Recalculate summary with 2 items
triggerEvent(elements.receiveAllBtn, 'click');
assert.strictEqual(elements.totalInTransitEl.textContent, '17.5', 'Total in-transit must be 17.5');
assert.strictEqual(elements.totalReceivedEl.textContent, '17.5', 'Total received must be 17.5');

// On Item 2: type discrepancy = 2.5
item2.discrepancyInput.value = '2.5';
triggerEvent(item2.discrepancyInput, 'input');
assert.strictEqual(item2.receivedInput.value, '10', 'Item 2 received must update to 10 (12.5 - 2.5)');
assert.strictEqual(elements.totalReceivedEl.textContent, '15', 'Total received must be 5 + 10 = 15');
assert.strictEqual(elements.totalDiscrepancyEl.textContent, '2.5', 'Total discrepancy must be 2.5');
assert.ok(elements.alertContainerEl.innerHTML.includes('Catatan Pengiriman'), 'Alert state must be Yellow');
assert.strictEqual(elements.submitBtnEl.disabled, false, 'Submit button remains enabled');

// On Item 1: manual input received = 5.0001 (floating point test within EPSILON)
item1.receivedInput.value = '5.000005';
triggerEvent(item1.receivedInput, 'input');
assert.ok(!item1.row.classList.contains('table-danger'), 'Values within EPSILON must NOT trigger false exceeded error');

// On Item 1: manual input received = 5.1 (exceeds EPSILON)
item1.receivedInput.value = '5.1';
triggerEvent(item1.receivedInput, 'input');
assert.ok(item1.row.classList.contains('table-danger'), 'Exceeding by 0.1 MUST trigger exceeded error');
assert.strictEqual(elements.submitBtnEl.disabled, true, 'Submit button must be disabled when any row exceeds');

console.log('-> PASS: Multi-item, decimal calculations, and epsilon tolerance verified.');

console.log('\n=== ALL 5 EMPIRICAL JS STRESS SCENARIOS PASSED WITH ZERO ERRORS ===');
