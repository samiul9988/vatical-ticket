// ==UserScript==
// @name         Vatican ticket panel opener
// @namespace    vatican-ticket-admin
// @version      1.9
// @description  Opens the ticket panel chosen on the admin dashboard (#book=<ticketId>). Never submits, proceeds or pays.
// @match        https://tickets.museivaticani.va/*
// @run-at       document-start
// @grant        none
// ==/UserScript==
(() => {
    const storageKey = 'vaticanAutoBook';
    const hashParams = new URLSearchParams(location.hash.slice(1));

    if (hashParams.get('book')) {
        sessionStorage.setItem(storageKey, JSON.stringify({
            id: hashParams.get('book'),
            title: hashParams.get('title'),
            full: hashParams.get('full'),
            reduced: hashParams.get('reduced'),
            lang: hashParams.get('lang'),
            time: hashParams.get('time'),
            at: Date.now(),
        }));
    }

    const pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    if (!pending || Date.now() - pending.at > 120000) return;

    let banner;
    const say = (text, color = '#1f2937') => {
        console.log('[auto-book]', text);
        if (!document.body) return;
        banner ??= Object.assign(document.createElement('div'), { style: 'position:fixed;left:50%;top:12px;transform:translateX(-50%);z-index:99999;padding:10px 18px;border-radius:999px;font:600 14px system-ui;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.3)' });
        banner.style.background = color;
        banner.textContent = `Auto-book: ${text}`;
        if (!banner.isConnected) document.body.appendChild(banner);
    };

    const fire = (el) => ['mousedown', 'mouseup', 'click'].forEach((type) => el.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, view: window })));
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const until = async (fn, timeout = 30000) => {
        const started = Date.now();
        while (Date.now() - started < timeout) {
            const value = await fn();
            if (value) return value;
            await wait(300);
        }
        return null;
    };

    const findCard = () => {
        const byId = document.getElementById(`ticket_${pending.id}`);
        if (byId) return byId;
        const wanted = (pending.title || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (!wanted) return null;
        return Array.from(document.querySelectorAll('[id^="ticket_"]')).find((el) => !el.id.startsWith('ticket_dx_') && el.textContent.replace(/\s+/g, ' ').toLowerCase().includes(wanted)) ?? null;
    };

    const normalise = (text) => text.replace(/\s+/g, ' ').trim().toLowerCase();

    // The site uses its own <app-dropdown>: a readonly text input plus a <section class="select__list"> of clickable <div>s.
    const dropdownInput = (dropdown) => dropdown.querySelector('input.muvaInput') || dropdown.querySelector('input[type="text"]');

    const chooseOption = async (dropdown, matcher) => {
        const input = dropdownInput(dropdown);
        if (!input) return false;
        if (matcher(input.value.trim())) return true;

        fire(input);
        const option = await until(() => Array.from(dropdown.querySelectorAll('section div')).find((o) => o.children.length < 3 && o.textContent.trim() !== '' && matcher(o.textContent.trim())), 3000);
        if (!option) {
            fire(input);
            return false;
        }
        fire(option);
        await wait(400);
        return matcher(input.value.trim());
    };

    const findDropdown = (label) => {
        const dropdowns = Array.from(document.querySelectorAll('app-dropdown'));
        return dropdowns.find((dropdown) => {
            let node = dropdown.parentElement;
            for (let i = 0; i < 6 && node && node !== document.body; i++, node = node.parentElement) {
                if (new RegExp(label, 'i').test(node.textContent) && node.querySelectorAll('app-dropdown').length === 1) return true;
            }
            return false;
        });
    };

    const fillPanel = async () => {
        const results = {};
        const quantity = (value) => (text) => normalise(text) === String(value);
        const full = findDropdown('full price');
        const reduced = findDropdown('reduced');
        const language = document.querySelector('input[data-cy="visitLang"]')?.closest('app-dropdown') || findDropdown('visit language');

        if (full && pending.full) results.full = await chooseOption(full, quantity(pending.full));
        if (reduced && pending.reduced) results.reduced = await chooseOption(reduced, quantity(pending.reduced));
        if (language && pending.lang) {
            const wanted = normalise(pending.lang);
            results.language = await chooseOption(language, (text) => {
                const t = normalise(text);
                return t !== '-' && t.length > 1 && (t === wanted || t.slice(0, 3) === wanted.slice(0, 3));
            });
        }
        console.log('[auto-book] fill results', results, { full: !!full, reduced: !!reduced, language: !!language });
        return { results, found: { full, reduced, language } };
    };

    // Each slot is <div class="muvaCalendarDayBorder"> holding <div class="muvaCalendarNumber">HH:MM</div> and, when sold out, <div class="muvaCalendarDaySoldOut">.
    const findSlot = () => Array.from(document.querySelectorAll('.muvaCalendarDayBorder'))
        .find((cell) => !cell.querySelector('.muvaCalendarDaySoldOut') && normalise(cell.querySelector('.muvaCalendarNumber')?.textContent ?? '') === pending.time);

    const pickTime = async () => {
        if (!pending.time) return true;
        let cell = findSlot();
        if (!cell) {
            const hour = Number(pending.time.split(':')[0]);
            const tab = Array.from(document.querySelectorAll('button, div, span, li, a')).find((el) => el.children.length === 0 && normalise(el.textContent) === (hour >= 12 ? 'afternoon' : 'morning'));
            if (tab) {
                fire(tab);
                await wait(600);
                cell = findSlot();
            }
        }
        if (!cell) return false;

        // The panel header shows "... | 30 September 2026 at 16:00" once the site has accepted the slot.
        const accepted = () => new RegExp(`\\bat ${pending.time}\\b`).test(document.body.textContent);

        cell.scrollIntoView({ block: 'center' });
        for (let attempt = 0; attempt < 3 && !accepted(); attempt++) {
            const current = findSlot() || cell;
            fire(current.querySelector('.muvaCalendarNumber') || current);
            await wait(700);
            if (!accepted()) {
                fire(current);
                await wait(700);
            }
        }
        console.log('[auto-book] time slot', pending.time, accepted() ? 'accepted by the site' : 'NOT accepted');
        if (!accepted()) return false;
        return true;
    };

    (async () => {
        await until(() => document.body);
        say('looking for the ticket…');

        const card = await until(findCard, 40000);
        if (!card) return say('ticket card not found on this page (it may not be listed for this date).', '#b91c1c');

        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        await wait(700);

        const footer = card.querySelector('[id^="ticket_dx_"]');
        const bookButton = Array.from((footer || card).querySelectorAll('button')).find((b) => !b.disabled && /book/i.test(b.textContent));
        if (!bookButton) return say('Book button is disabled or missing (ticket may be sold out).', '#b45309');

        sessionStorage.removeItem(storageKey);
        say('opening ticket…', '#047857');
        fire(bookButton);

        const opened = await until(() => /select the tickets for participants/i.test(document.body.textContent) && document.querySelector('app-dropdown'), 15000);
        if (!opened) return say('panel did not open. Click Book manually.', '#b91c1c');

        say('filling quantity and language…', '#047857');
        let results = {};
        for (let i = 0; i < 6; i++) {
            const outcome = await fillPanel();
            results = outcome.results;
            const missing = ['full', 'reduced', 'lang'].filter((key) => pending[key] && pending[key] !== '0').length;
            if (Object.values(results).length >= missing && Object.values(results).every(Boolean)) break;
            await wait(700);
        }
        const failed = Object.values(results).some((ok) => !ok);

        if (pending.time) {
            const picked = await until(() => pickTime(), 10000);
            say(picked && !failed ? `done. Time ${pending.time} selected. Click PROCEED yourself.` : `check the form: ${failed ? 'quantity/language not set. ' : ''}${picked ? '' : `time ${pending.time} not found. `}Then click PROCEED yourself.`, picked && !failed ? '#047857' : '#b45309');
        } else {
            say(failed ? 'quantity/language not fully set, check the form. Then click PROCEED yourself.' : 'done. Choose a time and click PROCEED yourself.', failed ? '#b45309' : '#047857');
        }
        await wait(6000);
        banner?.remove();
    })();
})();
