import { Controller } from '@hotwired/stimulus';
import { isoToSlDate, slDateToIso } from '../js/common/format';

export default class extends Controller {
    static targets = ['display', 'native'];

    connect() {
        this.updatingFromDisplay = false;
        this.hookNativeValue();
        this.syncFromNative();
        this.isoAtFocus = this.nativeTarget.value;
    }

    hookNativeValue() {
        const native = this.nativeTarget;
        const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
        const controller = this;

        Object.defineProperty(native, 'value', {
            configurable: true,
            enumerable: descriptor.enumerable,
            get() {
                return descriptor.get.call(this);
            },
            set(value) {
                descriptor.set.call(this, value);
                if (!controller.updatingFromDisplay) {
                    controller.syncFromNative();
                }
            },
        });
    }

    syncFromNative() {
        if (!this.hasDisplayTarget || !this.hasNativeTarget) {
            return;
        }
        this.displayTarget.value = isoToSlDate(this.nativeTarget.value);
        this.displayTarget.disabled = this.nativeTarget.disabled;
        this.displayTarget.readOnly = this.nativeTarget.readOnly;
    }

    onDisplayFocus() {
        this.isoAtFocus = this.nativeTarget.value;
    }

    onDisplayInput() {
        const iso = slDateToIso(this.displayTarget.value);
        if (!iso || iso === this.nativeTarget.value) {
            return;
        }
        this.updatingFromDisplay = true;
        this.nativeTarget.value = iso;
        this.updatingFromDisplay = false;
    }

    onDisplayBlur() {
        const typed = this.displayTarget.value.trim();
        const iso = slDateToIso(typed);
        this.updatingFromDisplay = true;
        this.nativeTarget.value = iso;
        this.updatingFromDisplay = false;
        this.syncFromNative();
        if (iso === this.isoAtFocus) {
            return;
        }
        this.nativeTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.nativeTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    onNativeChange() {
        this.syncFromNative();
    }

    openPicker() {
        this.nativeTarget.showPicker?.();
    }
}
