import { Controller } from '@hotwired/stimulus';
import { isoToSlDate, slDateToIso } from '../js/common/format';

export default class extends Controller {
    static targets = ['display', 'native', 'icon'];

    connect() {
        this.updatingFromDisplay = false;
        this.openingPicker = false;
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
        if (this.hasIconTarget) {
            this.iconTarget.disabled = this.nativeTarget.disabled || this.nativeTarget.readOnly;
        }
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
        if (typed !== '' && iso === '') {
            this.syncFromNative();
            return;
        }
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

    onDisplayKeydown(event) {
        if (event.key !== 'ArrowDown' || !event.altKey) {
            return;
        }
        this.openPicker(event);
    }

    onNativeFocus() {
        if (this.openingPicker || !this.hasDisplayTarget || this.displayTarget.disabled) {
            return;
        }
        this.displayTarget.focus();
    }

    onNativeChange() {
        this.syncFromNative();
    }

    openPicker(event) {
        event?.preventDefault();
        const native = this.nativeTarget;
        if (native.disabled || native.readOnly) {
            return;
        }

        this.openingPicker = true;
        try {
            if (typeof native.showPicker === 'function') {
                native.showPicker();
                return;
            }
            native.focus();
        } catch {
            this.openingPicker = false;
            if (!this.displayTarget.disabled) {
                this.displayTarget.focus();
            }
        } finally {
            window.setTimeout(() => {
                this.openingPicker = false;
            }, 0);
        }
    }
}
