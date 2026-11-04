import { Controller } from '@hotwired/stimulus';

const WIDTH = 320;
const HEIGHT = 210;
const PLOT = { left: 30, right: 10, top: 16, bottom: 28 };

/*
 * Edits the caesura points (a Symfony form collection) and draws a live preview of the grade curve.
 * The server applies the same rules when the form is submitted; this only gives instant feedback.
 */
export default class extends Controller {
    static targets = ['list', 'template', 'point', 'percentage', 'grade', 'pass', 'chart', 'problem', 'removeButton'];
    static values = { index: Number, defaultPoints: Array, defaultPass: Number };

    connect() {
        this.render();
    }

    add() {
        const row = this.createRow();
        this.listTarget.append(row);
        row.querySelector('input')?.focus();
        this.render();
    }

    remove({ currentTarget }) {
        if (this.pointTargets.length <= 2) {
            return;
        }

        const row = currentTarget.closest('[data-caesura-target="point"]');
        row.classList.add('is-leaving');
        row.addEventListener('animationend', () => {
            row.remove();
            this.render();
        }, { once: true });
    }

    reset() {
        this.listTarget.replaceChildren(...this.defaultPointsValue.map((point) => {
            const row = this.createRow();
            row.querySelector('[data-caesura-target="percentage"]').value = point.percentage;
            row.querySelector('[data-caesura-target="grade"]').value = point.grade;

            return row;
        }));
        this.passTarget.value = this.defaultPassValue;
        this.render();
    }

    update() {
        this.render();
    }

    createRow() {
        const html = this.templateTarget.innerHTML.replaceAll('__point__', String(this.indexValue++));

        return document.createRange().createContextualFragment(html.trim()).firstElementChild;
    }

    render() {
        const points = this.readPoints();
        const pass = Number.parseFloat(this.passTarget.value);
        const problem = findProblem(points, pass);

        this.problemTarget.hidden = problem === null;
        this.problemTarget.textContent = problem ?? '';
        this.removeButtonTargets.forEach((button) => {
            button.disabled = this.pointTargets.length <= 2;
        });

        this.chartTarget.innerHTML = drawChart(points, pass, problem !== null, !this.hasDrawn);
        this.hasDrawn = true;
    }

    readPoints() {
        return this.pointTargets
            .map((row) => ({
                percentage: Number.parseFloat(row.querySelector('[data-caesura-target="percentage"]').value),
                grade: Number.parseFloat(row.querySelector('[data-caesura-target="grade"]').value),
            }))
            .filter((point) => Number.isFinite(point.percentage) && Number.isFinite(point.grade))
            .sort((a, b) => a.percentage - b.percentage);
    }
}

function findProblem(points, pass) {
    if (points.length < 2) {
        return 'Fill in at least two points.';
    }
    if (points.some((point) => point.percentage < 0 || point.percentage > 100)) {
        return 'Percentages must be between 0 and 100.';
    }
    const duplicate = points.find((point, index) => index > 0 && point.percentage === points[index - 1].percentage);
    if (duplicate) {
        return `Two points use ${duplicate.percentage}%.`;
    }
    if (points.some((point, index) => index > 0 && point.grade < points[index - 1].grade)) {
        return 'Grades must not go down as the score goes up.';
    }
    if (!Number.isFinite(pass) || pass <= 0 || pass > 100) {
        return 'The pass percentage must be above 0 and at most 100.';
    }

    return null;
}

/**
 * The same interpolation as the server's GradeCalculator: linear between points, clamped outside them.
 */
function gradeAt(points, percentage) {
    if (percentage <= points[0].percentage) {
        return points[0].grade;
    }
    for (let i = 1; i < points.length; i++) {
        const lower = points[i - 1];
        const upper = points[i];
        if (percentage <= upper.percentage) {
            return lower.grade + (percentage - lower.percentage) / (upper.percentage - lower.percentage) * (upper.grade - lower.grade);
        }
    }

    return points.at(-1).grade;
}

function drawChart(points, pass, invalid, animate) {
    const plotWidth = WIDTH - PLOT.left - PLOT.right;
    const plotHeight = HEIGHT - PLOT.top - PLOT.bottom;
    const grades = points.length > 0 ? points.map((point) => point.grade) : [1, 10];
    const lowest = Math.floor(Math.min(...grades));
    const highest = Math.max(Math.ceil(Math.max(...grades)), lowest + 1);
    const x = (percentage) => round(PLOT.left + Math.min(100, Math.max(0, percentage)) / 100 * plotWidth);
    const y = (grade) => round(PLOT.top + (1 - (grade - lowest) / (highest - lowest)) * plotHeight);
    const gradeStep = Math.max(1, Math.ceil((highest - lowest) / 5));

    let svg = '<title>Preview of the caesura: grade by score percentage</title>'
        + '<defs><linearGradient id="caesura-fill" x1="0" x2="0" y1="0" y2="1">'
        + '<stop offset="0%" stop-color="#9785ff" stop-opacity="0.32"/><stop offset="100%" stop-color="#9785ff" stop-opacity="0.02"/>'
        + '</linearGradient></defs><g class="caesura-chart__grid">';
    for (let grade = lowest; grade <= highest; grade += gradeStep) {
        svg += `<line x1="${PLOT.left}" x2="${WIDTH - PLOT.right}" y1="${y(grade)}" y2="${y(grade)}"/>`
            + `<text class="caesura-chart__tick" x="${PLOT.left - 8}" y="${y(grade)}" dy="0.32em" text-anchor="end">${grade}</text>`;
    }
    for (const percentage of [0, 25, 50, 75, 100]) {
        svg += `<text class="caesura-chart__tick" x="${x(percentage)}" y="${HEIGHT - 8}" text-anchor="middle">${percentage}%</text>`;
    }
    svg += '</g>';

    const validPass = Number.isFinite(pass) && pass > 0 && pass <= 100;
    if (validPass) {
        svg += `<rect class="caesura-chart__pass-zone" x="${x(pass)}" y="${PLOT.top}" width="${round(x(100) - x(pass))}" height="${plotHeight}"/>`;
    }

    if (points.length >= 2) {
        const curve = [[0, points[0].grade], ...points.map((point) => [point.percentage, point.grade]), [100, points.at(-1).grade]]
            .map(([percentage, grade]) => `${x(percentage)},${y(grade)}`);
        const intro = animate ? ' is-intro' : '';
        svg += `<path class="caesura-chart__area${intro}" d="M${curve.join(' L')} L${x(100)},${y(lowest)} L${x(0)},${y(lowest)} Z"/>`
            + `<path class="caesura-chart__curve${invalid ? ' is-invalid' : ''}${intro}" pathLength="1" d="M${curve.join(' L')}"/>`;
        svg += points.map((point) => `<circle class="caesura-chart__point" cx="${x(point.percentage)}" cy="${y(point.grade)}" r="4.5"/>`).join('');

        if (validPass && !invalid) {
            const passGrade = gradeAt(points, pass);
            svg += `<line class="caesura-chart__pass-line" x1="${x(pass)}" x2="${x(pass)}" y1="${PLOT.top - 4}" y2="${PLOT.top + plotHeight}"/>`
                + `<circle class="caesura-chart__pass-point" cx="${x(pass)}" cy="${y(passGrade)}" r="5.5"/>`
                + `<text class="caesura-chart__pass-label" x="${x(pass) - 6}" y="${PLOT.top + 6}" text-anchor="end">pass ${formatNumber(pass)}% → ${passGrade.toFixed(1)}</text>`;
        }
    }

    return svg;
}

function round(value) {
    return Math.round(value * 100) / 100;
}

function formatNumber(value) {
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}
