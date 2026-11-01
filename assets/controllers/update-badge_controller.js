import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge'];

    static values = {
        statusUrl: String,
    };

    connect() {
        this.load();
    }

    async load() {
        try {
            const response = await fetch(this.statusUrlValue, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            if (data.updateAvailable && this.hasBadgeTarget) {
                this.badgeTarget.hidden = false;
            }
        } catch (error) {
            // The menu stays usable when the release check cannot run.
        }
    }
}
