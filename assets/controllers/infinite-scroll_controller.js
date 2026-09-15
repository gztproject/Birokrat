import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tableBody', 'cards', 'sentinel', 'status'];
    static values = {
        page: { type: Number, default: 1 },
        lastPage: { type: Number, default: 1 },
        loadingLabel: { type: String, default: '' },
        endLabel: { type: String, default: '' },
    };

    connect() {
        this.loading = false;
        this.observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                this.fillViewport();
            }
        }, { root: this.element, rootMargin: '80px' });
        if (this.hasSentinelTarget) {
            this.observer.observe(this.sentinelTarget);
        }
        this.fillViewport();
    }

    disconnect() {
        this.observer?.disconnect();
    }

    canLoad() {
        return !this.loading && this.pageValue < this.lastPageValue;
    }

    needsMore() {
        const { scrollHeight, clientHeight, scrollTop } = this.element;
        return scrollHeight <= clientHeight + 16
            || scrollTop + clientHeight + 80 >= scrollHeight;
    }

    async fillViewport() {
        while (this.canLoad() && this.needsMore()) {
            const loaded = await this.loadMore();
            if (!loaded) {
                break;
            }
        }
    }

    async loadMore() {
        if (!this.canLoad()) {
            return false;
        }
        this.loading = true;
        this.showStatus(this.loadingLabelValue);

        const url = new URL(window.location.pathname, window.location.origin);
        new URLSearchParams(window.location.search).forEach((value, key) => {
            if (key !== 'partial') {
                url.searchParams.set(key, value);
            }
        });
        url.searchParams.set('page', String(this.pageValue + 1));
        url.searchParams.set('partial', '1');

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error(response.statusText);
            }
            const data = await response.json();
            const nextPage = data.page ?? this.pageValue + 1;
            if (nextPage <= this.pageValue) {
                this.lastPageValue = this.pageValue;
                this.loading = false;
                this.showStatus(this.endLabelValue);
                return false;
            }
            if (data.rows && this.hasTableBodyTarget) {
                this.tableBodyTarget.insertAdjacentHTML('beforeend', data.rows);
            }
            if (data.cards && this.hasCardsTarget) {
                this.cardsTarget.insertAdjacentHTML('beforeend', data.cards);
            }
            this.pageValue = nextPage;
            this.lastPageValue = data.lastPage ?? this.lastPageValue;
        } catch {
            this.loading = false;
            this.showStatus('');
            return false;
        }

        this.loading = false;
        if (this.pageValue >= this.lastPageValue) {
            this.showStatus(this.endLabelValue);
        } else {
            this.showStatus('');
        }
        return true;
    }

    showStatus(text) {
        if (!this.hasStatusTarget) {
            return;
        }
        this.statusTarget.textContent = text;
        this.statusTarget.hidden = !text;
    }
}
