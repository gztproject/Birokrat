import { Controller } from '@hotwired/stimulus';

const STEPS = ['requirements', 'fetch', 'checkout', 'composer', 'frontend', 'migrate', 'cache'];

export default class extends Controller {
    static targets = [
        'channelButton',
        'channelWarning',
        'checking',
        'summary',
        'currentVersion',
        'latestVersion',
        'releaseLink',
        'summaryText',
        'discardWarning',
        'targetWarning',
        'prodWarning',
        'error',
        'checkButton',
        'startButton',
        'notesCard',
        'notes',
        'progressCard',
        'progressValue',
        'progressBar',
        'progressLabel',
        'runningNotice',
        'log',
        'reloadButton',
        'retryButton',
    ];

    static values = {
        statusUrl: String,
        channelUrl: String,
        runUrl: String,
        token: String,
        checkFailed: String,
        startFailed: String,
        upToDate: String,
        none: String,
        available: String,
    };

    connect() {
        this.running = false;
        this.outcome = 'idle';
        this.canStart = false;
        this.currentStep = null;
        this.onLeave = (event) => {
            if (!this.running) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', this.onLeave);
        this.refresh(false);
    }

    disconnect() {
        window.removeEventListener('beforeunload', this.onLeave);
    }

    check() {
        if (this.running) {
            return;
        }
        this.refresh(true);
    }

    async choose(event) {
        if (this.running) {
            return;
        }

        const channel = event.params.channel;
        this.highlightChannel(channel);
        this.showChecking();

        try {
            const response = await fetch(this.channelUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({
                    token: this.tokenValue,
                    channel,
                }),
            });

            if (!response.ok) {
                await this.refresh(false);
                this.errorTarget.hidden = false;
                this.errorTarget.textContent = this.checkFailedValue;
                return;
            }
        } catch (error) {
            this.showError(this.checkFailedValue);
            return;
        }

        await this.refresh(true);
    }

    async start() {
        if (this.running || this.startButtonTarget.disabled) {
            return;
        }

        this.running = true;
        this.outcome = 'running';
        this.lock(true);
        this.resetProgress();
        this.progressCardTarget.hidden = false;
        this.reloadButtonTarget.hidden = true;
        this.retryButtonTarget.hidden = true;
        this.logTarget.textContent = '';

        try {
            await this.stream();
        } catch (error) {
            this.fail(error.message || this.startFailedValue);
        } finally {
            this.running = false;
            this.lock(false);
            this.startButtonTarget.disabled = !this.canStart || this.outcome === 'success';
            this.reloadButtonTarget.hidden = this.outcome !== 'success';
            this.retryButtonTarget.hidden = this.outcome !== 'failed';
        }
    }

    retry() {
        this.start();
    }

    reload() {
        window.location.reload();
    }

    async refresh(force) {
        this.showChecking();
        const url = new URL(this.statusUrlValue, window.location.origin);
        if (force) {
            url.searchParams.set('refresh', '1');
        }

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error(this.checkFailedValue);
            }
            this.render(await response.json());
        } catch (error) {
            this.showError(this.checkFailedValue);
        }
    }

    render(data) {
        this.checkingTarget.hidden = true;
        this.highlightChannel(data.channel);
        this.channelWarningTarget.hidden = data.channel !== 'prerelease';

        if (data.error) {
            this.showError(this.checkFailedValue);
            return;
        }

        this.errorTarget.hidden = true;
        this.summaryTarget.hidden = false;
        this.currentVersionTarget.textContent = data.currentVersion || '';
        this.showLatest(data.latestVersion, data.releaseUrl);
        this.notesCardTarget.hidden = !data.notesHtml;
        this.notesTarget.innerHTML = data.notesHtml || '';

        const available = Boolean(data.updateAvailable);
        this.canStart = available && Boolean(data.canUpdate);
        this.discardWarningTarget.hidden = !available;
        this.targetWarningTarget.hidden = !(available && data.prerelease);
        this.prodWarningTarget.hidden = !(available && !data.canUpdate);
        this.startButtonTarget.disabled = !this.canStart;

        if (!data.latestVersion) {
            this.summaryTextTarget.textContent = this.noneValue;
        } else if (available) {
            this.summaryTextTarget.textContent = this.availableValue.replaceAll('__VERSION__', data.latestVersion);
        } else {
            this.summaryTextTarget.textContent = this.upToDateValue;
        }
    }

    showLatest(version, url) {
        const label = version || '—';
        const safe = typeof url === 'string' && url.startsWith('https://github.com/gztproject/Birokrat/');

        this.latestVersionTarget.hidden = safe;
        this.latestVersionTarget.textContent = label;
        this.releaseLinkTarget.hidden = !safe;
        if (safe) {
            this.releaseLinkTarget.href = url;
            this.releaseLinkTarget.textContent = label;
        }
    }

    showChecking() {
        this.checkingTarget.hidden = false;
        this.summaryTarget.hidden = true;
        this.errorTarget.hidden = true;
        this.notesCardTarget.hidden = true;
        this.startButtonTarget.disabled = true;
    }

    showError(message) {
        this.checkingTarget.hidden = true;
        this.summaryTarget.hidden = true;
        this.errorTarget.hidden = false;
        this.errorTarget.textContent = message;
        this.canStart = false;
        this.startButtonTarget.disabled = true;
    }

    highlightChannel(channel) {
        this.channelButtonTargets.forEach((button) => {
            const selected = button.dataset.channel === channel;
            button.classList.toggle('btn-primary', selected);
            button.classList.toggle('btn-outline-primary', !selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    }

    lock(locked) {
        this.channelButtonTargets.forEach((button) => {
            button.disabled = locked;
        });
        this.checkButtonTarget.disabled = locked;
        if (locked) {
            this.startButtonTarget.disabled = true;
        }
    }

    resetProgress() {
        this.currentStep = null;
        this.element.querySelectorAll('[data-step]').forEach((item) => {
            item.dataset.state = 'pending';
        });
        this.progressBarTarget.classList.add('progress-bar-animated', 'progress-bar-striped');
        this.progressBarTarget.classList.remove('bg-danger', 'bg-success');
        this.runningNoticeTarget.hidden = false;
        this.setProgress(0, '');
    }

    setProgress(progress, message) {
        const value = Math.max(0, Math.min(100, Number(progress) || 0));
        const text = `${value}%`;
        this.progressValueTarget.textContent = text;
        this.progressBarTarget.style.width = text;
        this.progressBarTarget.textContent = text;
        this.progressBarTarget.setAttribute('aria-valuenow', String(value));
        this.progressLabelTarget.textContent = message || '';
    }

    markStep(step, failed = false) {
        if (step && step !== 'done') {
            this.currentStep = step;
        }

        const index = STEPS.indexOf(step);
        this.element.querySelectorAll('[data-step]').forEach((item) => {
            const itemIndex = STEPS.indexOf(item.dataset.step);
            if (step === 'done') {
                item.dataset.state = 'done';
                return;
            }
            if (failed && item.dataset.step === (step || this.currentStep)) {
                item.dataset.state = 'failed';
                return;
            }
            if (index === -1) {
                return;
            }
            if (itemIndex < index) {
                item.dataset.state = 'done';
            } else if (itemIndex === index) {
                item.dataset.state = failed ? 'failed' : 'current';
            } else {
                item.dataset.state = 'pending';
            }
        });
    }

    appendLog(message) {
        this.logTarget.append(document.createTextNode(`${message}\n`));
        this.logTarget.scrollTop = this.logTarget.scrollHeight;
    }

    fail(message) {
        this.outcome = 'failed';
        this.markStep(this.currentStep, true);
        this.appendLog(message);
        this.progressBarTarget.classList.remove('progress-bar-animated');
        this.progressBarTarget.classList.add('bg-danger');
        this.runningNoticeTarget.hidden = true;
    }

    handleEvent(event) {
        switch (event.type) {
        case 'meta':
            this.appendLog(`Updating from ${event.currentVersion} to ${event.targetVersion}.`);
            break;
        case 'progress':
            this.markStep(event.step);
            this.setProgress(event.progress, event.message);
            break;
        case 'output':
            this.appendLog(event.channel === 'stderr' ? `[stderr] ${event.message}` : event.message);
            break;
        case 'complete':
            this.outcome = 'success';
            this.markStep('done');
            this.setProgress(100, event.message);
            this.progressBarTarget.classList.remove('progress-bar-animated');
            this.progressBarTarget.classList.add('bg-success');
            this.runningNoticeTarget.hidden = true;
            break;
        case 'failed':
            this.fail(event.message || this.startFailedValue);
            this.setProgress(event.progress, event.message);
            break;
        default:
            this.appendLog(JSON.stringify(event));
            break;
        }
    }

    async stream() {
        const response = await fetch(this.runUrlValue, {
            method: 'POST',
            headers: {
                Accept: 'application/x-ndjson',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({ token: this.tokenValue }),
        });

        if (!response.ok) {
            throw new Error(this.startFailedValue);
        }

        if (!response.body || typeof response.body.getReader !== 'function') {
            this.consumeLines(await response.text());
            return;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';
            this.consumeLines(lines.join('\n'));
            if (done) {
                break;
            }
        }

        if (buffer.trim() !== '') {
            this.consumeLines(buffer);
        }
    }

    consumeLines(chunk) {
        chunk.split('\n').filter((line) => line.trim() !== '').forEach((line) => {
            try {
                this.handleEvent(JSON.parse(line));
            } catch (error) {
                this.appendLog(line);
            }
        });
    }
}
