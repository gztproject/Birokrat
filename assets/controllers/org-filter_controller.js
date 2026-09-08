import { Controller } from '@hotwired/stimulus';
import { isoDateFromUnix, unixEndOfDay, unixStartOfDay, withQuery } from '../js/common/filters/query';

export default class extends Controller {
    static targets = ['organization', 'from', 'to', 'year'];

    async connect() {
        try {
            await this.loadOrganizations();
        } catch {
            // Keep hydrating dates even if the organization list fails.
        }
        this.hydrate();
    }

    hydrate() {
        const params = new URLSearchParams(window.location.search);
        if (this.hasYearTarget) {
            this.yearTarget.value = params.get('year') ?? '';
        }
        if (this.hasFromTarget) {
            this.fromTarget.value = isoDateFromUnix(params.get('dateFrom'));
        }
        if (this.hasToTarget) {
            this.toTarget.value = isoDateFromUnix(params.get('dateTo'));
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

    apply(event) {
        event?.preventDefault();
        if (this.hasYearTarget && this.yearTarget.value && document.activeElement === this.yearTarget) {
            this.onYearChange();
            return;
        }
        this.navigate({
            organization: this.hasOrganizationTarget ? this.organizationTarget.value : undefined,
            dateFrom: this.hasFromTarget ? this.unixOrClear(unixStartOfDay(this.fromTarget.value)) : undefined,
            dateTo: this.hasToTarget ? this.unixOrClear(unixEndOfDay(this.toTarget.value)) : undefined,
            year: this.hasYearTarget && this.yearTarget.value ? this.yearTarget.value : undefined,
        });
    }

    onOrganizationChange() {
        this.navigate({ organization: this.organizationTarget.value });
    }

    onYearChange() {
        if (!this.yearTarget.value) {
            this.navigate({ year: undefined, dateFrom: undefined, dateTo: undefined });
            return;
        }
        const year = Number(this.yearTarget.value);
        this.navigate({
            year: String(year),
            dateFrom: String(unixStartOfDay(`${year}-01-01`)),
            dateTo: String(unixEndOfDay(`${year}-12-31`)),
        });
    }

    onFromChange() {
        this.navigate({
            dateFrom: this.unixOrClear(unixStartOfDay(this.fromTarget.value)),
            year: undefined,
        });
    }

    onToChange() {
        this.navigate({
            dateTo: this.unixOrClear(unixEndOfDay(this.toTarget.value)),
            year: undefined,
        });
    }

    clear() {
        window.location = window.location.pathname;
    }

    unixOrClear(value) {
        return value === undefined ? undefined : String(value);
    }

    navigate(params) {
        window.location = withQuery(window.location.href, params);
    }
}
