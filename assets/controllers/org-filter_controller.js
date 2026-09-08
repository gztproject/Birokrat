import { Controller } from '@hotwired/stimulus';

function unixStartOfDay(value) {
    return Math.floor(new Date(`${value}T00:00:00`).getTime() / 1000);
}

function unixEndOfDay(value) {
    return Math.floor(new Date(`${value}T23:59:59`).getTime() / 1000);
}

function withQuery(url, params) {
    const parsed = new URL(url, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === '') {
            parsed.searchParams.delete(key);
        } else {
            parsed.searchParams.set(key, value);
        }
    });
    parsed.searchParams.delete('page');
    return parsed.pathname + parsed.search;
}

export default class extends Controller {
    static targets = ['organization', 'from', 'to', 'year'];

    async connect() {
        await this.loadOrganizations();
        const params = new URLSearchParams(window.location.search);
        if (this.hasYearTarget && params.get('year')) {
            this.yearTarget.value = params.get('year');
        }
        if (this.hasFromTarget) {
            this.fromTarget.value = params.get('dateFrom')
                ? new Date(Number(params.get('dateFrom')) * 1000).toISOString().slice(0, 10)
                : `${new Date().getFullYear()}-01-01`;
        }
        if (this.hasToTarget) {
            this.toTarget.value = params.get('dateTo')
                ? new Date(Number(params.get('dateTo')) * 1000).toISOString().slice(0, 10)
                : new Date().toISOString().slice(0, 10);
        }
        if (this.hasOrganizationTarget && params.get('organization')) {
            this.organizationTarget.value = params.get('organization');
        }
    }

    async loadOrganizations() {
        if (!this.hasOrganizationTarget) {
            return;
        }
        const response = await fetch('/dashboard/organization/list');
        const data = await response.json();
        this.organizationTarget.innerHTML = '';
        this.organizationTarget.append(new Option('*', ''));
        data[0].data.organizations.forEach((org) => {
            this.organizationTarget.append(new Option(org.name, org.id));
        });
    }

    onOrganizationChange() {
        window.location = withQuery(window.location.href, { organization: this.organizationTarget.value });
    }

    onYearChange() {
        if (!this.yearTarget.value) {
            return;
        }
        const year = Number(this.yearTarget.value);
        window.location = withQuery(window.location.href, {
            year: String(year),
            dateFrom: String(unixStartOfDay(`${year}-01-01`)),
            dateTo: String(unixEndOfDay(`${year}-12-31`)),
        });
    }

    onFromChange() {
        window.location = withQuery(window.location.href, {
            dateFrom: String(unixStartOfDay(this.fromTarget.value)),
            year: undefined,
        });
    }

    onToChange() {
        window.location = withQuery(window.location.href, {
            dateTo: String(unixEndOfDay(this.toTarget.value)),
            year: undefined,
        });
    }

    clear() {
        window.location = window.location.pathname;
    }
}
