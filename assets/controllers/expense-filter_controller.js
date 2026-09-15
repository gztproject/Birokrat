import { Controller } from '@hotwired/stimulus';
import { isoDateFromUnix, unixEndOfDay, unixStartOfDay, withQuery } from '../js/common/filters/query';

export default class extends Controller {
    static targets = ['from', 'to', 'booked', 'unbooked'];
    static values = { table: String };

    connect() {
        const params = new URLSearchParams(window.location.search);
        if (this.hasFromTarget) {
            this.fromTarget.value = isoDateFromUnix(params.get('dateFrom'));
        }
        if (this.hasToTarget) {
            this.toTarget.value = isoDateFromUnix(params.get('dateTo'));
        }
        if (this.hasBookedTarget) {
            this.bookedTarget.checked = params.get('booked') === 'true';
        }
        if (this.hasUnbookedTarget) {
            this.unbookedTarget.checked = params.get('unbooked') !== 'false';
        }
    }

    apply(event) {
        event?.preventDefault();
        this.navigate({
            dateFrom: this.hasFromTarget ? this.unixOrClear(unixStartOfDay(this.fromTarget.value)) : undefined,
            dateTo: this.hasToTarget ? this.unixOrClear(unixEndOfDay(this.toTarget.value)) : undefined,
        });
    }

    onFromChange() {
        this.navigate({
            dateFrom: this.unixOrClear(unixStartOfDay(this.fromTarget.value)),
        });
    }

    onToChange() {
        this.navigate({
            dateTo: this.unixOrClear(unixEndOfDay(this.toTarget.value)),
        });
    }

    onBookedChange() {
        this.navigate({ booked: String(this.bookedTarget.checked) });
    }

    onUnbookedChange() {
        this.navigate({ unbooked: String(this.unbookedTarget.checked) });
    }

    clear() {
        window.location = window.location.pathname;
    }

    selectAll() {
        const boxes = [...document.querySelectorAll('input.TECheckBox[type="checkbox"]')]
            .filter((box) => box.offsetParent !== null);
        const anyChecked = boxes.some((box) => box.checked);
        boxes.forEach((box) => {
            box.checked = !anyChecked;
            box.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    unixOrClear(value) {
        return value === undefined ? undefined : String(value);
    }

    navigate(params) {
        window.location = withQuery(window.location.href, params);
    }
}
