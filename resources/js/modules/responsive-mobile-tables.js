const TABLE_SELECTOR = 'table:not([data-mobile-table="off"]):not(.gt-mobile-table-disabled)';
const ACTION_HEADING = /^(aksi|action|actions|opsi|kelola|resolve|approval)$/i;
const NUMERIC_HEADING = /(on hand|reserved|rusak|damaged|available|tersedia|stok|stock|qty|jumlah|total|saldo|nilai|nominal|target|bonus|limit|piutang|paid|balance|margin|hpp|harga|pencapaian|minimum|safety)/i;
const EMPTY_VALUE = /^(?:-|—|–|belum ada|tidak ada)$/i;

let tableSequence = 0;
let rowSequence = 0;
let observer;
const scheduledTables = new Set();

const normalizeText = (value) => String(value || '')
    .replace(/\s+/g, ' ')
    .replace(/\s*:\s*activate to sort.*$/i, '')
    .trim();

const textFrom = (element) => normalizeText(element?.textContent);

const headerMatrix = (table) => {
    const rows = Array.from(table.tHead?.rows || []);
    const grid = [];

    if (!rows.length) {
        const columnCount = Math.max(
            0,
            ...Array.from(table.tBodies).flatMap((body) => Array.from(body.rows).map((row) => row.cells.length)),
        );

        return Array.from({ length: columnCount }, (_, columnIndex) => ({
            cell: null,
            label: `Detail ${columnIndex + 1}`,
        }));
    }

    rows.forEach((row, rowIndex) => {
        grid[rowIndex] ||= [];
        let columnIndex = 0;

        Array.from(row.cells).forEach((cell) => {
            while (grid[rowIndex][columnIndex]) columnIndex += 1;

            const columnSpan = Math.max(1, cell.colSpan || 1);
            const rowSpan = Math.max(1, cell.rowSpan || 1);
            const label = normalizeText(cell.dataset.mobileLabel || textFrom(cell));

            for (let y = rowIndex; y < rowIndex + rowSpan; y += 1) {
                grid[y] ||= [];
                for (let x = columnIndex; x < columnIndex + columnSpan; x += 1) {
                    grid[y][x] = { cell, label };
                }
            }

            columnIndex += columnSpan;
        });
    });

    const columnCount = Math.max(0, ...grid.map((row) => row.length));

    return Array.from({ length: columnCount }, (_, columnIndex) => {
        const labels = grid
            .map((row) => row[columnIndex]?.label)
            .filter((label, index, all) => label && all.indexOf(label) === index);

        return {
            cell: [...grid].reverse().find((row) => row[columnIndex])?.[columnIndex]?.cell,
            label: labels.join(' · ') || `Detail ${columnIndex + 1}`,
        };
    });
};

const contentElement = (cell) => cell?.querySelector(':scope > .gt-mobile-cell-value > .gt-mobile-detail-content') || cell;

const summarySource = (cell, role) => cell?.parentElement?.querySelector(`[data-mobile-${role}]`) || contentElement(cell);

const summaryText = (cell, role) => {
    const source = summarySource(cell, role);
    const clone = source?.cloneNode(true);

    if (!clone) return '';

    clone.querySelectorAll('script, style, .gt-mobile-card-trigger, [hidden], .d-none').forEach((element) => element.remove());
    clone.querySelectorAll('button, input, select, textarea').forEach((element) => {
        const replacement = document.createTextNode(
            element.getAttribute('aria-label') || element.getAttribute('title') || '',
        );
        element.replaceWith(replacement);
    });

    return normalizeText(clone.textContent);
};

const configuredColumn = (table, role, headers) => {
    const headerIndex = headers.findIndex(({ cell }) => cell?.dataset.mobileRole?.split(/\s+/).includes(role));
    if (headerIndex >= 0) return headerIndex;

    const configured = table.dataset[`mobile${role.charAt(0).toUpperCase()}${role.slice(1)}`];
    if (!configured) return -1;

    if (/^\d+$/.test(configured)) return Number(configured);

    const normalized = normalizeText(configured).toLocaleLowerCase('id-ID');
    return headers.findIndex(({ label }) => label.toLocaleLowerCase('id-ID').includes(normalized));
};

const firstHeaderMatch = (headers, patterns, excluded = []) => {
    for (const pattern of patterns) {
        const index = headers.findIndex(({ label }, candidate) => !excluded.includes(candidate) && pattern.test(label));
        if (index >= 0) return index;
    }

    return -1;
};

const firstMeaningfulCell = (cells, headers, excluded = []) => cells.findIndex((cell, index) => (
    !excluded.includes(index)
    && !ACTION_HEADING.test(headers[index]?.label || '')
    && !EMPTY_VALUE.test(summaryText(cell, 'value'))
));

const summaryColumns = (table, cells, headers) => {
    const primary = configuredColumn(table, 'primary', headers);
    const primaryIndex = primary >= 0 ? primary : firstHeaderMatch(headers, [
        /^(kode|sku|nomor|no\.?|dokumen|order|invoice|produk|pengguna|karyawan|supplier|customer|pelanggan|role|alert)/i,
    ]);
    const safePrimary = primaryIndex >= 0 ? primaryIndex : firstMeaningfulCell(cells, headers);

    const secondary = configuredColumn(table, 'secondary', headers);
    const secondaryIndex = secondary >= 0 ? secondary : firstHeaderMatch(headers, [
        /^(nama|nama\/usaha|produk|pelanggan|customer|supplier|karyawan|user|email|deskripsi)/i,
    ], [safePrimary]);
    const safeSecondary = secondaryIndex >= 0
        ? secondaryIndex
        : firstMeaningfulCell(cells, headers, [safePrimary]);

    const subtitle = configuredColumn(table, 'subtitle', headers);
    const subtitleIndex = subtitle >= 0 ? subtitle : firstHeaderMatch(headers, [
        /(lokasi|cabang|gudang|tanggal|waktu|periode|kategori|jenis|shift|kasir|ring)/i,
    ], [safePrimary, safeSecondary]);
    const safeSubtitle = subtitleIndex >= 0
        ? subtitleIndex
        : firstMeaningfulCell(cells, headers, [safePrimary, safeSecondary]);

    const highlight = configuredColumn(table, 'highlight', headers);
    const highlightIndex = highlight >= 0 ? highlight : firstHeaderMatch(headers, [
        /(available|tersedia)/i,
        /(stok total|total stok|saldo)/i,
        /(^|\s)(total|nominal|nilai|jumlah|qty|pencapaian)(\s|$)/i,
        /status/i,
    ], [safePrimary, safeSecondary, safeSubtitle]);

    return {
        primary: safePrimary,
        secondary: safeSecondary,
        subtitle: safeSubtitle,
        highlight: highlightIndex,
    };
};

const createSummaryLine = (className, value) => {
    const element = document.createElement('span');
    element.className = className;
    element.textContent = value;
    return element;
};

const markSummaryDuplicates = (row, cells, columns) => {
    row.querySelectorAll('.gt-mobile-summary-source').forEach((element) => {
        element.classList.remove('gt-mobile-summary-source');
    });
    cells.forEach((cell) => cell.classList.remove('gt-mobile-summary-duplicate'));

    Object.entries(columns).forEach(([role, columnIndex]) => {
        if (columnIndex < 0 || !cells[columnIndex]) return;

        summarySource(cells[columnIndex], role)?.classList.add('gt-mobile-summary-source');
    });

    cells.forEach((cell) => {
        const content = contentElement(cell);
        if (!content) return;

        if (content.classList.contains('gt-mobile-summary-source')) {
            cell.classList.add('gt-mobile-summary-duplicate');
            return;
        }

        const clone = content.cloneNode(true);
        clone.querySelectorAll('.gt-mobile-summary-source, script, style, [hidden], .d-none').forEach((element) => element.remove());

        const remainingText = normalizeText(clone.textContent)
            .replace(/^[\s|/,:;\-\u00b7\u2022\u2013\u2014]+|[\s|/,:;\-\u00b7\u2022\u2013\u2014]+$/g, '')
            .trim();
        const hasRemainingMedia = Boolean(clone.querySelector('img, picture, video, audio, canvas, svg'));

        if (!remainingText && !hasRemainingMedia) cell.classList.add('gt-mobile-summary-duplicate');
    });
};

const createTrigger = (table, row, cells, headers) => {
    const columns = summaryColumns(table, cells, headers);
    const trigger = document.createElement('button');
    const top = document.createElement('span');
    const footer = document.createElement('span');
    const identity = document.createElement('span');
    const chevron = document.createElement('span');
    const controlledIds = cells
        .map((cell) => cell.querySelector(':scope > .gt-mobile-cell-value')?.id)
        .filter(Boolean);

    trigger.type = 'button';
    trigger.className = 'gt-mobile-card-trigger';
    trigger.setAttribute('aria-expanded', row.classList.contains('is-mobile-expanded') ? 'true' : 'false');
    trigger.setAttribute('aria-controls', controlledIds.join(' '));

    top.className = 'gt-mobile-card-top';
    identity.className = 'gt-mobile-card-identity';
    footer.className = 'gt-mobile-card-footer';
    chevron.className = 'gt-mobile-card-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    chevron.innerHTML = '<svg viewBox="0 0 20 20" focusable="false"><path d="m5 7.5 5 5 5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>';

    const primary = summaryText(cells[columns.primary], 'primary') || 'Lihat detail';
    const secondary = summaryText(cells[columns.secondary], 'secondary');
    const subtitle = summaryText(cells[columns.subtitle], 'subtitle');
    const highlight = summaryText(cells[columns.highlight], 'highlight');
    const highlightLabel = row.querySelector('[data-mobile-highlight]')?.dataset.mobileHighlightLabel
        || headers[columns.highlight]?.label
        || '';

    identity.appendChild(createSummaryLine('gt-mobile-card-primary', primary));
    if (secondary && secondary !== primary) {
        identity.appendChild(createSummaryLine('gt-mobile-card-secondary', secondary));
    }

    top.append(identity, chevron);
    if (subtitle) footer.appendChild(createSummaryLine('gt-mobile-card-subtitle', subtitle));

    if (highlight) {
        const important = createSummaryLine('gt-mobile-card-highlight', highlight);
        if (highlightLabel) important.dataset.label = highlightLabel;
        footer.appendChild(important);
    }

    trigger.append(top);
    if (footer.childElementCount) trigger.append(footer);
    trigger.dataset.mobileSignature = JSON.stringify({ primary, secondary, subtitle, highlight, highlightLabel });
    markSummaryDuplicates(row, cells, columns);

    return trigger;
};

const wrapCell = (tableId, rowId, cell, columnIndex, label) => {
    let wrapper = cell.querySelector(':scope > .gt-mobile-cell-value');

    if (!wrapper) {
        const content = document.createElement('div');
        const detailLabel = document.createElement('span');
        wrapper = document.createElement('div');

        wrapper.className = 'gt-mobile-cell-value';
        content.className = 'gt-mobile-detail-content';
        detailLabel.className = 'gt-mobile-detail-label';

        Array.from(cell.childNodes)
            .filter((node) => !(node instanceof Element && node.classList.contains('gt-mobile-card-trigger')))
            .forEach((node) => content.appendChild(node));

        wrapper.append(detailLabel, content);
        cell.appendChild(wrapper);
    }

    wrapper.id ||= `${tableId}-row-${rowId}-detail-${columnIndex}`;
    const detailLabel = wrapper.querySelector(':scope > .gt-mobile-detail-label');
    if (detailLabel && detailLabel.textContent !== label) detailLabel.textContent = label;

    cell.dataset.mobileLabel = label;
    cell.classList.toggle('gt-mobile-actions', ACTION_HEADING.test(label));
    cell.classList.toggle('gt-mobile-numeric', NUMERIC_HEADING.test(label) && !ACTION_HEADING.test(label));
};

const enhanceRow = (table, row, headers) => {
    const cells = Array.from(row.cells).filter((cell) => cell.tagName === 'TD');
    if (!cells.length || row.classList.contains('child')) return;

    const isSpanningRow = cells.length === 1 && cells[0].colSpan > 1;
    row.classList.toggle('gt-mobile-spanning-row', isSpanningRow);

    if (isSpanningRow) {
        row.querySelector('.gt-mobile-card-trigger')?.remove();
        return;
    }

    if (!row.dataset.mobileRowId) {
        rowSequence += 1;
        row.dataset.mobileRowId = String(rowSequence);
    }

    cells.forEach((cell, columnIndex) => {
        wrapCell(table.dataset.mobileTableId, row.dataset.mobileRowId, cell, columnIndex, headers[columnIndex]?.label || `Detail ${columnIndex + 1}`);
    });

    const firstCell = cells[0];
    const currentTrigger = firstCell.querySelector(':scope > .gt-mobile-card-trigger');
    const nextTrigger = createTrigger(table, row, cells, headers);

    firstCell.classList.add('gt-mobile-summary-host');
    if (!currentTrigger) {
        firstCell.prepend(nextTrigger);
    } else if (currentTrigger.dataset.mobileSignature !== nextTrigger.dataset.mobileSignature) {
        currentTrigger.replaceWith(nextTrigger);
    }
};

const enhanceTable = (table) => {
    if (!table.tBodies.length) return;

    const headers = headerMatrix(table);
    if (headers.length < 2 && table.dataset.mobileTable !== 'force') return;

    if (!table.dataset.mobileTableId) {
        tableSequence += 1;
        table.dataset.mobileTableId = table.id || `gt-mobile-table-${tableSequence}`;
    }

    table.classList.add('gt-mobile-ready');
    table.closest('.table-responsive')?.classList.add('gt-mobile-table-wrapper');

    Array.from(table.tBodies).forEach((body) => {
        Array.from(body.rows).forEach((row) => enhanceRow(table, row, headers));
    });
};

const scheduleEnhance = (table) => {
    if (!(table instanceof HTMLTableElement) || scheduledTables.has(table)) return;

    scheduledTables.add(table);
    window.requestAnimationFrame(() => {
        scheduledTables.delete(table);
        if (table.isConnected) enhanceTable(table);
    });
};

const setExpanded = (row, expanded) => {
    row.classList.toggle('is-mobile-expanded', expanded);
    row.querySelector(':scope > td > .gt-mobile-card-trigger')
        ?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
};

const handleAccordionClick = (event) => {
    const trigger = event.target.closest('.gt-mobile-card-trigger');
    if (!trigger) return;

    const row = trigger.closest('tr');
    const table = trigger.closest('table');
    if (!row || !table) return;

    const willExpand = trigger.getAttribute('aria-expanded') !== 'true';

    if (willExpand && table.dataset.mobileAccordion !== 'multiple') {
        table.querySelectorAll('tbody tr.is-mobile-expanded').forEach((openRow) => {
            if (openRow !== row) setExpanded(openRow, false);
        });
    }

    setExpanded(row, willExpand);
};

const observeTables = () => {
    if (observer || !document.body) return;

    observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            const table = mutation.target instanceof Element ? mutation.target.closest('table') : null;
            if (table?.matches(TABLE_SELECTOR)) scheduleEnhance(table);

            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;

                if (node.matches(TABLE_SELECTOR)) scheduleEnhance(node);
                node.querySelectorAll?.(TABLE_SELECTOR).forEach(scheduleEnhance);
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
};

export const initializeResponsiveTables = (root = document) => {
    root.querySelectorAll(TABLE_SELECTOR).forEach(enhanceTable);
    observeTables();

    if (document.body?.dataset.mobileTableListener !== 'true') {
        document.body.dataset.mobileTableListener = 'true';
        document.addEventListener('click', handleAccordionClick);
    }
};

export const refreshResponsiveTable = (table) => {
    if (table?.matches?.(TABLE_SELECTOR)) scheduleEnhance(table);
};
