/**
 * Global Number Input Stepper
 * Memastikan input number kuantitas menambah dan mengurang bilangan bulat (+1 / -1)
 * saat tombol panah keyboard atau spinner browser ditekan, sembari tetap mendukung
 * nilai desimal jika diketik manual.
 */

function countDecimals(value) {
    const str = String(value);
    if (!str.includes('.')) return 0;
    return str.split('.')[1].length;
}

function adjustNumberInput(input, delta) {
    if (input.disabled || input.readOnly) return;

    const currentValStr = input.value.trim();
    const min = input.getAttribute('min') !== null ? parseFloat(input.getAttribute('min')) : null;
    const max = input.getAttribute('max') !== null ? parseFloat(input.getAttribute('max')) : null;

    let currentVal;
    let decimals = 0;

    if (currentValStr === '') {
        currentVal = delta > 0 ? (min !== null && min > 0 ? min : 0) : 0;
    } else {
        currentVal = parseFloat(currentValStr);
        if (isNaN(currentVal)) {
            currentVal = 0;
        } else {
            decimals = countDecimals(currentValStr);
        }
    }

    let nextVal = currentVal + delta;

    if (min !== null && nextVal < min) {
        nextVal = min;
    }

    if (max !== null && nextVal > max) {
        nextVal = max;
    }

    // Hindari floating point error (misal 1.5 + 1 = 2.5)
    if (decimals > 0) {
        input.value = parseFloat(nextVal.toFixed(Math.min(decimals, 4))).toString();
    } else {
        input.value = Math.round(nextVal).toString();
    }

    // Trigger standard input & change event
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

export function initializeNumberInputStepper(root = document) {
    // 1. Ubah step="0.0001" menjadi step="any" agar browser spinner tidak melompat 0.0001
    const convertSteps = (container) => {
        const inputs = container.querySelectorAll('input[type="number"][step="0.0001"]');
        inputs.forEach(input => {
            // Kecuali jika ada atribut data-preserve-step="true"
            if (!input.dataset.preserveStep) {
                input.setAttribute('step', 'any');
            }
        });
    };

    convertSteps(root);

    // 2. Observer untuk form/modal dinamis (seperti modal katalog atau baris baru tabel)
    if (window.MutationObserver && root === document) {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            if (node.matches && node.matches('input[type="number"][step="0.0001"]')) {
                                if (!node.dataset.preserveStep) node.setAttribute('step', 'any');
                            }
                            if (node.querySelectorAll) {
                                convertSteps(node);
                            }
                        }
                    });
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    // 3. Listener keyboard ArrowUp & ArrowDown untuk input bertipe number
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;

        const target = e.target;
        if (!target || target.tagName !== 'INPUT' || target.type !== 'number') return;

        // Cek apakah input dikecualikan (misal persentase pajak kecil jika ada flag khusus)
        if (target.dataset.preserveStep === 'true') return;

        const stepAttr = target.getAttribute('step');
        // Hanya terapkan jika step="any" atau tidak ada step atau step desimal
        if (stepAttr === 'any' || stepAttr === '0.0001' || !stepAttr) {
            e.preventDefault();
            adjustNumberInput(target, e.key === 'ArrowUp' ? 1 : -1);
        }
    });
}
