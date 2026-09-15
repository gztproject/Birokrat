import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['board', 'widget', 'hiddenBar', 'hiddenList', 'reset'];
    static values = {
        user: { type: String, default: '' },
        showLabel: { type: String, default: '' },
    };

    connect() {
        this.initialOrder = this.widgetTargets.map((widget) => widget.dataset.widgetId);
        this.draggingId = null;
        this.apply(this.load());
        this.syncChrome();
    }

    hide(event) {
        event.preventDefault();
        const widget = this.widgetById(event.params.id);
        if (!widget) {
            return;
        }
        widget.hidden = true;
        this.persist();
    }

    show(event) {
        event.preventDefault();
        const widget = this.widgetById(event.params.id);
        if (!widget) {
            return;
        }
        widget.hidden = false;
        this.persist();
    }

    reset(event) {
        event.preventDefault();
        localStorage.removeItem(this.storageKey());
        this.widgetTargets.forEach((widget) => {
            widget.hidden = false;
        });
        this.defaultOrder().forEach((id) => {
            const widget = this.widgetById(id);
            if (widget) {
                this.boardTarget.appendChild(widget);
            }
        });
        this.syncChrome();
    }

    dragStart(event) {
        const widget = event.target.closest('[data-dashboard-layout-target="widget"]');
        if (!widget || widget.hidden) {
            event.preventDefault();
            return;
        }
        this.draggingId = widget.dataset.widgetId;
        widget.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', this.draggingId);
    }

    dragEnd() {
        this.widgetTargets.forEach((widget) => widget.classList.remove('is-dragging', 'is-drop-target'));
        this.draggingId = null;
    }

    dragOver(event) {
        if (!this.draggingId) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const target = event.target.closest('[data-dashboard-layout-target="widget"]');
        this.widgetTargets.forEach((widget) => {
            widget.classList.toggle('is-drop-target', widget === target && widget.dataset.widgetId !== this.draggingId);
        });
    }

    drop(event) {
        event.preventDefault();
        const dragged = this.widgetById(this.draggingId);
        const target = event.target.closest('[data-dashboard-layout-target="widget"]');
        if (!dragged || !target || dragged === target) {
            this.dragEnd();
            return;
        }
        const rect = target.getBoundingClientRect();
        if (event.clientX > rect.left + rect.width / 2) {
            target.after(dragged);
        } else {
            target.before(dragged);
        }
        this.dragEnd();
        this.persist();
    }

    persist() {
        localStorage.setItem(this.storageKey(), JSON.stringify(this.snapshot()));
        this.syncChrome();
    }

    apply(state) {
        state.order.forEach((id) => {
            const widget = this.widgetById(id);
            if (widget) {
                this.boardTarget.appendChild(widget);
            }
        });
        this.widgetTargets.forEach((widget) => {
            widget.hidden = state.hidden.includes(widget.dataset.widgetId);
        });
    }

    snapshot() {
        return {
            order: this.widgetTargets.map((widget) => widget.dataset.widgetId),
            hidden: this.widgetTargets
                .filter((widget) => widget.hidden)
                .map((widget) => widget.dataset.widgetId),
        };
    }

    load() {
        try {
            const raw = localStorage.getItem(this.storageKey());
            if (!raw) {
                return { order: this.defaultOrder(), hidden: [] };
            }
            const data = JSON.parse(raw);
            return {
                order: Array.isArray(data.order) ? data.order : this.defaultOrder(),
                hidden: Array.isArray(data.hidden) ? data.hidden : [],
            };
        } catch {
            return { order: this.defaultOrder(), hidden: [] };
        }
    }

    defaultOrder() {
        return [...(this.initialOrder ?? this.widgetTargets.map((widget) => widget.dataset.widgetId))];
    }

    widgetById(id) {
        return this.widgetTargets.find((widget) => widget.dataset.widgetId === id) ?? null;
    }

    storageKey() {
        return `birokrat.dashboard.layout.${this.userValue || 'anon'}`;
    }

    syncChrome() {
        const hidden = this.widgetTargets.filter((widget) => widget.hidden);
        const customized = hidden.length > 0 || this.orderChanged();
        if (this.hasHiddenBarTarget) {
            this.hiddenBarTarget.hidden = hidden.length === 0;
        }
        if (this.hasHiddenListTarget) {
            this.hiddenListTarget.replaceChildren(...hidden.map((widget) => this.restoreButton(widget)));
        }
        if (this.hasResetTarget) {
            this.resetTarget.hidden = !customized;
        }
    }

    orderChanged() {
        const current = this.widgetTargets.map((widget) => widget.dataset.widgetId).join(',');
        return current !== this.defaultOrder().join(',');
    }

    restoreButton(widget) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary';
        button.dataset.action = 'dashboard-layout#show';
        button.dataset.dashboardLayoutIdParam = widget.dataset.widgetId;
        const title = widget.dataset.widgetTitle || widget.dataset.widgetId;
        button.textContent = `${this.showLabelValue} ${title}`.trim();
        return button;
    }
}
