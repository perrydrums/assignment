import { Controller } from '@hotwired/stimulus';

/*
 * Detail drawers for one student and one question. All text is inserted with textContent.
 */
export default class extends Controller {
    static targets = [
        'studentDialog', 'studentTitle', 'studentStats', 'studentRows', 'passedTemplate', 'failedTemplate',
        'questionDialog', 'questionTitle', 'questionStats', 'questionRows', 'questionGroupSection', 'questionGroupNote', 'questionGroups', 'questionActions',
    ];
    static values = {
        exam: Object,
        details: Array,
        adjustments: Array,
        labels: Object,
        reliability: String,
    };

    openStudent({ params: { index, grade, passed } }) {
        const student = this.examValue.students?.[index];
        if (!student) {
            return;
        }

        const questions = this.examValue.questions;
        const maximum = questions.reduce((sum, question, questionIndex) => sum + countedMaximum(question, this.adjustment(questionIndex)), 0);
        const total = student.scores.reduce((sum, score, questionIndex) => sum + countedScore(score, questions[questionIndex], this.adjustment(questionIndex)), 0);
        const showAdjustments = questions.some((_, questionIndex) => 'keep' !== this.adjustment(questionIndex));

        this.studentTitleTarget.textContent = student.id;
        this.studentStatsTarget.replaceChildren(
            stat(this.label('score'), scoreValue(total, maximum)),
            stat(this.label('grade'), format(grade, 1)),
            stat(this.label('result'), (passed ? this.passedTemplateTarget : this.failedTemplateTarget).content.cloneNode(true)),
        );
        this.studentRowsTarget.replaceChildren(...questions.map((question, questionIndex) => {
            const row = document.createElement('tr');
            row.append(
                cell(question.label, 'th'),
                cell(bar(student.scores[questionIndex] / question.maxScore, scoreValue(student.scores[questionIndex], question.maxScore)), 'td', 'num'),
            );
            if (showAdjustments) {
                const adjustment = this.adjustment(questionIndex);
                const label = document.createElement('span');
                label.className = 'keep' === adjustment ? 'muted' : 'badge';
                label.textContent = this.label(adjustment);
                row.append(cell(label));
            }

            return row;
        }));
        this.studentDialogTarget.showModal();
    }

    openQuestion({ params: { index } }) {
        const originalIndex = this.originalIndex(index);
        const question = this.examValue.questions?.[originalIndex];
        const detail = this.detailsValue[index];
        if (!question || !detail) {
            return;
        }

        const reliability = Number.parseFloat(this.reliabilityValue);
        this.questionTitleTarget.textContent = question.label;
        this.questionStatsTarget.replaceChildren(
            stat(this.label('difficulty'), this.metric(detail.pValue)),
            stat(this.label('discrimination'), this.metric(detail.ritValue)),
            stat(
                this.label('alpha_if_removed'),
                this.metric(detail.alphaIfRemoved),
                Number.isNaN(reliability) ? '' : this.label('alpha_now').replace('%alpha%', format(reliability, 3)),
            ),
        );

        const studentCount = this.examValue.students.length;
        this.questionRowsTarget.replaceChildren(...detail.distribution.map((entry) => {
            const share = document.createElement('span');
            share.className = 'muted';
            share.textContent = ` · ${formatPercent(entry.count / studentCount)}`;
            const value = document.createDocumentFragment();
            value.append(String(entry.count), share);

            const row = document.createElement('tr');
            row.append(cell(format(entry.score), 'th'), cell(bar(entry.count / studentCount, value), 'td', 'num'));

            return row;
        }));

        const group = detail.group;
        this.questionGroupSectionTarget.hidden = !group;
        if (group) {
            this.questionGroupNoteTarget.textContent = this.label('groups_note').replaceAll('%size%', String(group.size));
            this.questionGroupsTarget.replaceChildren(
                stat(this.label('high_group'), scoreValue(group.high, question.maxScore)),
                stat(this.label('low_group'), scoreValue(group.low, question.maxScore)),
            );
        }

        this.renderQuestionActions(originalIndex);
        this.questionDialogTarget.showModal();
    }

    renderQuestionActions(originalIndex) {
        const select = this.element.querySelector(`[data-adjustment-index="${originalIndex}"]`);
        const current = this.adjustment(originalIndex);
        const actions = select ? ['keep', 'remove', 'full_marks'].filter((value) => value !== current) : [];

        this.questionActionsTarget.replaceChildren(...actions.map((value) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button button--secondary';
            button.textContent = this.label(value);
            button.addEventListener('click', () => {
                select.value = value;
                select.form?.requestSubmit();
            });

            return button;
        }));
    }

    follow({ params: { eventName, detail } }) {
        window.dispatchEvent(new CustomEvent(eventName, { detail }));
    }

    close(event) {
        event.currentTarget.closest('dialog')?.close();
    }

    originalIndex(displayedIndex) {
        const map = this.element.dataset.originalIndexes;
        if (!map) {
            return displayedIndex;
        }

        return Number(map.split(',')[displayedIndex] ?? displayedIndex);
    }

    adjustment(questionIndex) {
        return this.adjustmentsValue[questionIndex] || 'keep';
    }

    metric(value) {
        return null === value || undefined === value ? this.label('not_available') : format(value, 3);
    }

    label(key) {
        return this.labelsValue[key] ?? key;
    }
}

function countedMaximum(question, adjustment) {
    return adjustment === 'remove' ? 0 : question.maxScore;
}

function countedScore(score, question, adjustment) {
    if (adjustment === 'remove') {
        return 0;
    }

    return adjustment === 'full_marks' ? question.maxScore : score;
}

function format(value, decimals) {
    const digits = undefined === decimals
        ? { maximumFractionDigits: 2 }
        : { minimumFractionDigits: decimals, maximumFractionDigits: decimals };

    return new Intl.NumberFormat(document.documentElement.lang || 'en', digits).format(value);
}

function formatPercent(ratio) {
    return new Intl.NumberFormat(document.documentElement.lang || 'en', { style: 'percent', maximumFractionDigits: 0 }).format(ratio);
}

function scoreValue(score, maximum) {
    const max = document.createElement('span');
    max.className = 'muted';
    max.textContent = ` / ${format(maximum)}`;
    const fragment = document.createDocumentFragment();
    fragment.append(format(score), max);

    return fragment;
}

function bar(ratio, content) {
    const track = document.createElement('span');
    track.className = 'score-bar';
    track.style.setProperty('--value', String(Math.max(0, Math.min(1, ratio || 0))));
    track.append(document.createElement('span'));
    const value = document.createElement('span');
    value.className = 'score-cell__value';
    value.append(content);
    const wrapper = document.createElement('span');
    wrapper.className = 'score-cell';
    wrapper.append(track, value);

    return wrapper;
}

function stat(label, value, note = '') {
    const term = document.createElement('dt');
    term.textContent = label;
    const description = document.createElement('dd');
    description.append(value);
    if (note) {
        const detail = document.createElement('span');
        detail.className = 'stats__note';
        detail.textContent = note;
        description.append(detail);
    }
    const item = document.createElement('div');
    item.className = 'stats__item';
    item.append(term, description);

    return item;
}

function cell(content, tag = 'td', className = '') {
    const element = document.createElement(tag);
    element.append(content);
    if (className) {
        element.className = className;
    }
    if ('th' === tag) {
        element.scope = 'row';
    }

    return element;
}
