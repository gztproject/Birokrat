import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'birokrat-theme';

export default class extends Controller {
    static targets = ['option'];

    connect() {
        this.media = window.matchMedia('(prefers-color-scheme: dark)');
        this.onSystemChange = () => this.apply(this.stored());
        this.media.addEventListener('change', this.onSystemChange);
        this.apply(this.stored());
        this.sync();
    }

    disconnect() {
        this.media?.removeEventListener('change', this.onSystemChange);
    }

    light(event) {
        event.preventDefault();
        this.persist('light');
    }

    dark(event) {
        event.preventDefault();
        this.persist('dark');
    }

    system(event) {
        event.preventDefault();
        this.persist('system');
    }

    persist(mode) {
        localStorage.setItem(STORAGE_KEY, mode);
        this.apply(mode);
        this.sync();
    }

    stored() {
        return localStorage.getItem(STORAGE_KEY) || 'system';
    }

    resolve(mode) {
        if (mode === 'dark' || mode === 'light') {
            return mode;
        }

        return this.media.matches ? 'dark' : 'light';
    }

    apply(mode) {
        document.documentElement.setAttribute('data-bs-theme', this.resolve(mode));
    }

    sync() {
        const mode = this.stored();
        this.optionTargets.forEach((option) => {
            option.classList.toggle('active', option.dataset.themeValue === mode);
            option.setAttribute('aria-checked', option.dataset.themeValue === mode ? 'true' : 'false');
        });
    }
}
