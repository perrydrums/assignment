import { Controller } from '@hotwired/stimulus';

const collator = new Intl.Collator('en', { numeric: true, sensitivity: 'base' });

/*
 * Sorting, searching, filtering and CSV export for a server-rendered table.
 *
 * Rows describe themselves: data-search (searchable text), data-tags (filter values) and data-grade
 * (for grade ranges). Cells may carry data-sort (sort value) and data-export (CSV value).
 */
export default class extends Controller {
    static targets = ['body', 'row', 'header', 'search', 'filterButton', 'count', 'empty', 'rangeChip', 'rangeLabel'];
    static values = { noun: String, filename: String };

    connect() {
        this.query = '';
        this.tag = 'all';
        this.range = null;
        this.apply();
    }

    search() {
        this.query = this.searchTarget.value.trim().toLowerCase();
        this.apply();
    }

    filter({ params: { tag } }) {
        this.tag = tag;
        this.filterButtonTargets.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.dataTableTagParam === tag));
        });
        this.apply();
    }

    filterRange({ detail }) {
        if (detail?.min === undefined || !this.hasRangeChipTarget) {
            return;
        }

        this.range = detail;
        this.rangeLabelTarget.textContent = detail.label;
        this.rangeChipTarget.hidden = false;
        this.apply();
        requestAnimationFrame(() => this.element.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    }

    clearRange() {
        this.range = null;
        if (this.hasRangeChipTarget) {
            this.rangeChipTarget.hidden = true;
        }
        this.apply();
        window.dispatchEvent(new CustomEvent('analysis:grade-filter-cleared'));
    }

    reset() {
        if (this.hasSearchTarget) {
            this.searchTarget.value = '';
        }
        this.query = '';
        this.filter({ params: { tag: 'all' } });
        this.clearRange();
    }

    sort({ currentTarget, params: { column, type } }) {
        const header = currentTarget.closest('th');
        const direction = header.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
        this.headerTargets.forEach((th) => th.removeAttribute('aria-sort'));
        header.setAttribute('aria-sort', direction);

        const sign = direction === 'ascending' ? 1 : -1;
        const valueOf = (row) => row.cells[column].dataset.sort ?? row.cells[column].textContent.trim();
        const rows = [...this.rowTargets].sort((a, b) => (type === 'number'
            ? compareNumbers(valueOf(a), valueOf(b), sign)
            : collator.compare(valueOf(a), valueOf(b)) * sign));

        this.bodyTarget.append(...rows);
        if (this.hasEmptyTarget) {
            this.bodyTarget.append(this.emptyTarget);
        }
    }

    focusRow({ detail }) {
        const row = this.rowTargets.find((candidate) => candidate.id === detail?.rowId);
        if (!row) {
            return;
        }

        if (row.hidden) {
            this.reset();
        }

        requestAnimationFrame(() => {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.remove('is-highlighted');
            void row.offsetWidth;
            row.classList.add('is-highlighted');
        });
    }

    exportCsv() {
        const header = this.headerTargets.map((th) => th.dataset.exportLabel ?? th.textContent.trim());
        const rows = this.rowTargets
            .filter((row) => !row.hidden)
            .map((row) => [...row.cells].map((cell) => cell.dataset.export ?? cell.textContent.trim()));
        const csv = [header, ...rows].map((cells) => cells.map(toCsvField).join(',')).join('\r\n');

        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob(['\ufeff', csv], { type: 'text/csv;charset=utf-8' }));
        link.download = this.filenameValue;
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    }

    apply() {
        let visible = 0;
        for (const row of this.rowTargets) {
            const matches = (this.query === '' || row.dataset.search.includes(this.query))
                && (this.tag === 'all' || row.dataset.tags.split(' ').includes(this.tag))
                && this.isInRange(row);
            row.hidden = !matches;
            visible += matches ? 1 : 0;
        }

        const total = this.rowTargets.length;
        this.countTarget.textContent = visible === total ? `${total} ${this.nounValue}` : `${visible} of ${total} ${this.nounValue}`;
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = visible > 0;
        }
    }

    isInRange(row) {
        if (this.range === null) {
            return true;
        }

        const grade = Number(row.dataset.grade);

        return grade >= this.range.min - 1e-9
            && (this.range.inclusive ? grade <= this.range.max + 1e-9 : grade < this.range.max - 1e-9);
    }
}

// Missing values (n/a) always sort last, whatever the direction.
function compareNumbers(a, b, sign) {
    const left = Number(a);
    const right = Number(b);
    if (Number.isNaN(left) || Number.isNaN(right)) {
        return Number.isNaN(left) - Number.isNaN(right);
    }

    return (left - right) * sign;
}

// Text starting with = + - @ would be executed as a formula by spreadsheet programs.
function toCsvField(value) {
    const safe = /^[=+\-@\t\r]/.test(value) && Number.isNaN(Number(value)) ? `'${value}` : value;

    return /[",\r\n]/.test(safe) ? `"${safe.replaceAll('"', '""')}"` : safe;
}
