// ==UserScript==
// @name         Vatican Ticket Auto Booker
// @namespace    vatican-ticket-admin
// @version      2.9
// @description  Opens selected Vatican ticket, fills options, selects time and reliably clicks PROCEED. Stops before payment.
// @match        https://tickets.museivaticani.va/*
// @run-at       document-start
// @grant        none
// ==/UserScript==

(() => {
    'use strict';

    const storageKey = 'vaticanAutoBook';

    const hashParams =
        new URLSearchParams(location.hash.slice(1));

    /*
     * ============================================================
     * BOOKING DATA
     * ============================================================
     */

    if (hashParams.get('book')) {

        sessionStorage.setItem(
            storageKey,
            JSON.stringify({
                id: hashParams.get('book'),
                title: hashParams.get('title'),
                full: hashParams.get('full'),
                reduced: hashParams.get('reduced'),
                lang: hashParams.get('lang'),
                time: hashParams.get('time'),
                at: Date.now()
            })
        );
    }

    const pending =
        JSON.parse(
            sessionStorage.getItem(
                storageKey
            ) || 'null'
        );

    if (!pending) return;

    if (
        !pending.at ||
        Date.now() - pending.at > 120000
    ) {
        return;
    }


    /*
     * ============================================================
     * STATUS
     * ============================================================
     */

    let banner = null;

    const say = (
        message,
        background = '#1f2937'
    ) => {

        console.log(
            '[auto-book]',
            message
        );

        if (!document.body) return;

        if (!banner) {

            banner =
                document.createElement(
                    'div'
                );

            banner.style.cssText = `
                position: fixed;
                left: 50%;
                top: 12px;
                transform: translateX(-50%);
                z-index: 2147483647;
                padding: 10px 18px;
                border-radius: 999px;
                font: 600 14px system-ui;
                color: #fff;
                box-shadow: 0 8px 24px rgba(0,0,0,.3);
                white-space: nowrap;
                pointer-events: none;
            `;

            document.body.appendChild(
                banner
            );
        }

        banner.style.background =
            background;

        banner.textContent =
            `Auto-book: ${message}`;
    };


    /*
     * ============================================================
     * HELPERS
     * ============================================================
     */

    const wait = ms =>
        new Promise(
            resolve =>
                setTimeout(
                    resolve,
                    ms
                )
        );


    const normalise = text =>
        String(text || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();


    const fire = element => {

        if (!element) return;

        const rect =
            element.getBoundingClientRect();

        const clientX =
            rect.left + rect.width / 2;

        const clientY =
            rect.top + rect.height / 2;

        const pointerEvents = [
            'pointerdown',
            'mousedown',
            'pointerup',
            'mouseup',
            'click'
        ];

        for (
            const type of pointerEvents
        ) {

            try {

                const isPointer =
                    type.startsWith(
                        'pointer'
                    );

                const EventType =
                    isPointer &&
                    typeof PointerEvent !==
                    'undefined'
                        ? PointerEvent
                        : MouseEvent;

                element.dispatchEvent(
                    new EventType(
                        type,
                        {
                            bubbles: true,
                            cancelable: true,
                            composed: true,
                            view: window,
                            buttons: 1,
                            clientX,
                            clientY,
                            ...(
                                isPointer
                                    ? {
                                        pointerId: 1,
                                        pointerType: 'mouse',
                                        isPrimary: true
                                    }
                                    : {}
                            )
                        }
                    )
                );

            } catch (e) {}
        }
    };


    const fireKeyActivate = element => {

        if (!element) return;

        try {
            element.focus({
                preventScroll: true
            });
        } catch (e) {}

        for (
            const type of [
                'keydown',
                'keyup'
            ]
        ) {

            try {

                element.dispatchEvent(
                    new KeyboardEvent(
                        type,
                        {
                            key: 'Enter',
                            code: 'Enter',
                            keyCode: 13,
                            which: 13,
                            bubbles: true,
                            cancelable: true
                        }
                    )
                );

            } catch (e) {}
        }
    };


    const until = async (
        callback,
        timeout = 30000,
        interval = 250
    ) => {

        const started =
            Date.now();

        while (
            Date.now() -
            started <
            timeout
        ) {

            try {

                const result =
                    await callback();

                if (result) {
                    return result;
                }

            } catch (e) {

                console.log(
                    '[auto-book] until error',
                    e
                );
            }

            await wait(interval);
        }

        return null;
    };


    /*
     * ============================================================
     * FIND TICKET
     * ============================================================
     */

    const findCard = () => {

        const direct =
            document.getElementById(
                `ticket_${pending.id}`
            );

        if (direct) {
            return direct;
        }

        const wanted =
            normalise(
                pending.title
            );

        if (!wanted) {
            return null;
        }

        return Array.from(
            document.querySelectorAll(
                '[id^="ticket_"]'
            )
        ).find(
            element => {

                if (
                    element.id.startsWith(
                        'ticket_dx_'
                    )
                ) {
                    return false;
                }

                return normalise(
                    element.textContent
                ).includes(
                    wanted
                );
            }
        ) || null;
    };


    /*
     * ============================================================
     * DROPDOWN
     * ============================================================
     */

    const dropdownInput =
        dropdown => {

            if (!dropdown) {
                return null;
            }

            return (
                dropdown.querySelector(
                    'input.muvaInput'
                ) ||
                dropdown.querySelector(
                    'input[type="text"]'
                )
            );
        };


    const findDropdown =
        label => {

            const dropdowns =
                Array.from(
                    document.querySelectorAll(
                        'app-dropdown'
                    )
                );

            return dropdowns.find(
                dropdown => {

                    let parent =
                        dropdown.parentElement;

                    for (
                        let i = 0;
                        i < 7 &&
                        parent &&
                        parent !== document.body;
                        i++,
                        parent =
                            parent.parentElement
                    ) {

                        if (
                            new RegExp(
                                label,
                                'i'
                            ).test(
                                parent.textContent
                            ) &&
                            parent.querySelectorAll(
                                'app-dropdown'
                            ).length === 1
                        ) {
                            return true;
                        }
                    }

                    return false;
                }
            ) || null;
        };


    const chooseOption =
        async (
            dropdown,
            matcher
        ) => {

            if (!dropdown) {
                return false;
            }

            const input =
                dropdownInput(
                    dropdown
                );

            if (!input) {
                return false;
            }

            if (
                matcher(
                    input.value
                )
            ) {
                return true;
            }

            try {

                input.click();

            } catch (e) {}

            fire(input);

            await wait(300);


            const option =
                await until(
                    () => {

                        const options =
                            Array.from(
                                dropdown.querySelectorAll(
                                    'section div'
                                )
                            );

                        return options.find(
                            element => {

                                const text =
                                    element.textContent
                                        ?.trim();

                                return (
                                    text &&
                                    element.children
                                        .length < 3 &&
                                    matcher(text)
                                );
                            }
                        ) || null;

                    },
                    2500,
                    200
                );


            if (!option) {
                return false;
            }


            try {

                option.scrollIntoView({
                    block: 'nearest'
                });

                await wait(100);

                option.click();

            } catch (e) {}

            fire(option);

            await wait(400);

            return matcher(
                input.value
            );
        };


    /*
     * ============================================================
     * FILL PANEL
     * ============================================================
     */

    const fillPanel =
        async () => {

            const results = {};

            const quantity =
                value =>
                    text =>
                        normalise(text) ===
                        String(value);


            /*
             * FULL
             */

            if (
                pending.full &&
                pending.full !== '0'
            ) {

                const full =
                    findDropdown(
                        'full price'
                    );

                if (full) {

                    results.full =
                        await chooseOption(
                            full,
                            quantity(
                                pending.full
                            )
                        );
                }
            }


            /*
             * REDUCED
             */

            if (
                pending.reduced &&
                pending.reduced !== '0'
            ) {

                const reduced =
                    findDropdown(
                        'reduced'
                    );

                if (reduced) {

                    results.reduced =
                        await chooseOption(
                            reduced,
                            quantity(
                                pending.reduced
                            )
                        );
                }
            }


            /*
             * LANGUAGE
             */

            if (pending.lang) {

                const language =
                    document.querySelector(
                        'input[data-cy="visitLang"]'
                    )?.closest(
                        'app-dropdown'
                    ) ||
                    findDropdown(
                        'visit language'
                    );


                if (language) {

                    const wanted =
                        normalise(
                            pending.lang
                        );

                    results.language =
                        await chooseOption(
                            language,
                            text => {

                                const value =
                                    normalise(
                                        text
                                    );

                                return (
                                    value !== '-' &&
                                    value.length > 1 &&
                                    (
                                        value === wanted ||
                                        value.slice(0, 3) ===
                                        wanted.slice(0, 3)
                                    )
                                );
                            }
                        );
                }
            }


            console.log(
                '[auto-book] fill results:',
                results
            );

            return results;
        };


    /*
     * ============================================================
     * FIND TIME SLOT
     * ============================================================
     */

    const findSlot =
        () => {

            if (!pending.time) {
                return null;
            }

            const wanted =
                normalise(
                    pending.time
                );

            return Array.from(
                document.querySelectorAll(
                    '.muvaCalendarDayBorder'
                )
            ).find(
                cell => {

                    const soldOut =
                        cell.querySelector(
                            '.muvaCalendarDaySoldOut'
                        );

                    const time =
                        normalise(
                            cell.querySelector(
                                '.muvaCalendarNumber'
                            )?.textContent
                        );

                    return (
                        !soldOut &&
                        time === wanted
                    );
                }
            ) || null;
        };


    /*
     * ============================================================
     * FIND TIME SLOT (generic fallback)
     *
     * Some ticket types (e.g. Hidden Sections) render a plain
     * grid of time buttons instead of the .muvaCalendarDayBorder
     * month calendar, so the specific selectors above find
     * nothing. This scans for any leaf element whose own text is
     * exactly the wanted time and isn't marked sold out nearby.
     * ============================================================
     */

    const findSlotGeneric =
        () => {

            if (!pending.time) {
                return null;
            }

            const wanted =
                normalise(
                    pending.time
                );

            const timeNode =
                Array.from(
                    document.querySelectorAll(
                        'button, div, span, li, a'
                    )
                ).find(
                    element =>
                        element.children.length === 0 &&
                        normalise(
                            element.textContent
                        ) === wanted
                );

            if (!timeNode) {
                return null;
            }

            /*
             * Prefer the most specific interactive ancestor.
             * Do NOT fall back to a generic parentElement, since
             * that can accidentally land on a shared row/grid
             * container wrapping several unrelated slots instead
             * of the one cell we actually want to click.
             */

            const target =
                timeNode.closest(
                    'button, [role="button"], [role="radio"], label'
                ) ||
                timeNode;

            /*
             * Scope the "sold out" check to the nearest small
             * wrapper around this specific slot (its direct
             * parent), not a container shared by every slot in
             * the row, which would always contain "sold out"
             * text for at least one sibling.
             */

            const scope =
                target.parentElement ||
                target;

            const soldOut =
                /sold\s*out/i.test(
                    scope.textContent ||
                    ''
                );

            if (soldOut) {
                return null;
            }

            if (
                target.disabled ||
                target.getAttribute(
                    'aria-disabled'
                ) === 'true'
            ) {
                return null;
            }

            return target;
        };


    /*
     * ============================================================
     * SLOT SELECTION VERIFIED
     *
     * After clicking a slot, check whether it visibly registered
     * as selected (aria-selected/aria-checked/checked/selected
     * class), so we know whether to keep retrying instead of
     * blindly assuming the click worked.
     * ============================================================
     */

    /*
     * The calendar cells have no aria-selected/aria-checked/radio
     * markers to inspect, so CSS-based "is it selected" checks are
     * unreliable on this site. Once a slot is accepted, though,
     * the panel header text itself updates to read something like
     * "... | 30 September 2026 at 16:00" — checking for that exact
     * "at HH:MM" phrase in the page text is a verified, working
     * signal (confirmed against the live site), unlike guessing at
     * CSS state.
     */

    const timeAccepted =
        () => {

            if (!pending.time) {
                return false;
            }

            const escaped =
                pending.time.replace(
                    /[.*+?^${}()|[\]\\]/g,
                    '\\$&'
                );

            return new RegExp(
                `\\bat ${escaped}\\b`
            ).test(
                document.body?.textContent ||
                ''
            );
        };


    /*
     * ============================================================
     * PICK TIME
     * ============================================================
     */

    const pickTime =
        async () => {

            if (!pending.time) {
                return true;
            }

            /*
             * The time-slot calendar can render asynchronously
             * (after a separate API call the site makes once
             * quantity/language are set), so a single synchronous
             * lookup right after fillPanel() can run before any
             * slot cells exist yet even though the wanted time
             * is genuinely on the page moments later. Poll for it
             * instead of checking once.
             */

            let cell =
                await until(
                    () =>
                        findSlot() ||
                        findSlotGeneric(),
                    10000,
                    300
                );


            /*
             * Morning / afternoon
             */

            if (!cell) {

                const hour =
                    Number(
                        pending.time
                            .split(':')[0]
                    );

                const tabName =
                    hour >= 12
                        ? 'afternoon'
                        : 'morning';


                const tab =
                    Array.from(
                        document.querySelectorAll(
                            'button,div,span,li,a'
                        )
                    ).find(
                        element =>
                            element.children.length === 0 &&
                            normalise(
                                element.textContent
                            ) === tabName
                    );


                if (tab) {

                    try {
                        tab.click();
                    } catch (e) {}

                    fire(tab);

                    cell =
                        await until(
                            () =>
                                findSlot() ||
                                findSlotGeneric(),
                            6000,
                            300
                        );
                }
            }


            if (!cell) {

                console.log(
                    '[auto-book] time not found:',
                    pending.time
                );

                return false;
            }


            /*
             * Scroll
             */

            try {

                cell.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

            } catch (e) {}

            await wait(300);


            /*
             * Click the slot, re-querying it fresh on every
             * attempt (the DOM can reflow after a click), and
             * verify against the panel header text actually
             * updating to "... at HH:MM" rather than guessing at
             * CSS selected-state classes that don't exist here.
             */

            for (
                let attempt = 0;
                attempt < 3 &&
                ! timeAccepted();
                attempt++
            ) {

                const current =
                    findSlot() ||
                    findSlotGeneric() ||
                    cell;

                const number =
                    current.querySelector?.(
                        '.muvaCalendarNumber'
                    );

                if (number) {

                    try {
                        number.click();
                    } catch (e) {}

                    fire(number);
                }

                await wait(700);

                if (! timeAccepted()) {

                    try {
                        current.click();
                    } catch (e) {}

                    fire(current);

                    await wait(700);
                }
            }

            const verified =
                timeAccepted();

            console.log(
                verified
                    ? '[auto-book] time selected (verified via page text):'
                    : '[auto-book] time click sent but NOT confirmed via page text:',
                pending.time
            );

            return verified;
        };


    /*
     * ============================================================
     * IMPORTANT:
     * EXACT PROCEED BUTTON FINDER
     *
     * Do NOT use generic [class*="button"] here.
     * That was causing false positives.
     * ============================================================
     */

    const findProceed =
        () => {

            const candidates = [];


            /*
             * 1. Real BUTTON elements
             */

            document
                .querySelectorAll(
                    'button'
                )
                .forEach(
                    button => {

                        const text =
                            normalise(
                                button.innerText ||
                                button.textContent ||
                                ''
                            );

                        if (
                            text === 'proceed' ||
                            text.startsWith(
                                'proceed'
                            )
                        ) {
                            candidates.push(
                                button
                            );
                        }
                    }
                );


            /*
             * 2. INPUT button
             */

            document
                .querySelectorAll(
                    'input[type="button"], input[type="submit"]'
                )
                .forEach(
                    input => {

                        const value =
                            normalise(
                                input.value
                            );

                        if (
                            value === 'proceed' ||
                            value.startsWith(
                                'proceed'
                            )
                        ) {
                            candidates.push(
                                input
                            );
                        }
                    }
                );


            /*
             * 3. role=button
             */

            document
                .querySelectorAll(
                    '[role="button"]'
                )
                .forEach(
                    element => {

                        const text =
                            normalise(
                                element.innerText ||
                                element.textContent ||
                                ''
                            );

                        if (
                            text === 'proceed' ||
                            text.startsWith(
                                'proceed'
                            )
                        ) {
                            candidates.push(
                                element
                            );
                        }
                    }
                );


            /*
             * Remove duplicates.
             */

            const unique =
                [...new Set(candidates)];


            if (unique.length === 0) {

                console.log(
                    '[auto-book] no PROCEED-like element found in DOM'
                );
            }


            /*
             * Only visible + enabled.
             */

            const visible =
                unique.filter(
                    element => {

                        const rect =
                            element.getBoundingClientRect();

                        const style =
                            getComputedStyle(
                                element
                            );


                        if (
                            rect.width <= 0 ||
                            rect.height <= 0
                        ) {
                            return false;
                        }


                        if (
                            style.display ===
                            'none' ||
                            style.visibility ===
                            'hidden' ||
                            style.opacity ===
                            '0'
                        ) {
                            return false;
                        }


                        if (
                            element.disabled
                        ) {
                            return false;
                        }


                        if (
                            element.getAttribute(
                                'aria-disabled'
                            ) === 'true'
                        ) {
                            return false;
                        }


                        return true;
                    }
                );


            /*
             * If several exist, choose the one
             * closest to the bottom/right area.
             *
             * The screenshot shows PROCEED at
             * bottom-right.
             */

            visible.sort(
                (a, b) => {

                    const ra =
                        a.getBoundingClientRect();

                    const rb =
                        b.getBoundingClientRect();

                    const scoreA =
                        ra.bottom +
                        ra.right;

                    const scoreB =
                        rb.bottom +
                        rb.right;

                    return (
                        scoreB -
                        scoreA
                    );
                }
            );


            const result =
                visible[0] || null;


            if (result) {

                console.log(
                    '[auto-book] exact PROCEED:',
                    result
                );

            } else if (unique.length > 0) {

                console.log(
                    '[auto-book] PROCEED found but not clickable (hidden or disabled):',
                    unique.map(
                        element => ({
                            tag: element.tagName,
                            disabled: !!element.disabled,
                            ariaDisabled:
                                element.getAttribute(
                                    'aria-disabled'
                                ),
                            text: normalise(
                                element.innerText ||
                                element.textContent
                            )
                        })
                    )
                );
            }


            return result;
        };


    /*
     * ============================================================
     * CHECK WHETHER NEXT STEP STARTED
     * ============================================================
     */

    const nextStepStarted =
        (
            startPath = null,
            initialButton = null
        ) => {

            /*
             * PRIMARY, most reliable signal:
             * the exact DOM node we clicked is gone. Angular
             * typically destroys/recreates the step container
             * (structural *ngIf) when moving to the next step,
             * so the old button element becomes detached from
             * the document. Unlike text-based checks, this can't
             * be fooled by breadcrumb labels that list upcoming
             * step names while still on the current step.
             */

            if (
                initialButton &&
                ! document.body.contains(
                    initialButton
                )
            ) {
                return true;
            }

            /*
             * A pathname change is a secondary, low-noise signal
             * (Angular route navigation), unlike full href which
             * also changes on harmless query/hash updates that
             * happen while still on the same step.
             */

            const pathChanged =
                startPath !== null &&
                location.pathname !==
                startPath;

            const body =
                document.body?.textContent ||
                '';

            const lower =
                body.toLowerCase();


            /*
             * The current ticket panel normally contains
             * these texts.
             */

            const currentPanelText =
                [
                    'select the time for the visit',
                    'please select to continue',
                    'additional services',
                    'included services'
                ];


            const stillOnCurrentPanel =
                currentPanelText.some(
                    text =>
                        lower.includes(text)
                );


            /*
             * Look for likely next-step text.
             *
             * IMPORTANT: only multi-word, highly specific
             * phrases belong here. Single generic words like
             * "participants", "payment" or "billing" routinely
             * appear in step breadcrumbs / footers / disclaimers
             * on the CURRENT page too, which previously caused
             * false "success" reports on the very first click.
             */

            const nextStepText =
                [
                    'customer details',
                    'personal details',
                    'contact details',
                    'reservation details',
                    'billing address',
                    'card number',
                    'enter your details',
                    'complete your booking'
                ];


            const hasNextStep =
                nextStepText.some(
                    text =>
                        lower.includes(text)
                );


            /*
             * Secondary signal:
             * current PROCEED disappeared.
             */

            const proceedStillVisible =
                !!findProceed();


            return (
                hasNextStep ||
                (
                    pathChanged &&
                    !proceedStillVisible
                ) ||
                (
                    !proceedStillVisible &&
                    !stillOnCurrentPanel
                )
            );
        };


    /*
     * ============================================================
     * RELIABLE PROCEED CLICK
     * ============================================================
     */

    const clickProceed =
        async () => {

            const startUrl =
                location.pathname;

            say(
                'finding PROCEED…',
                '#047857'
            );


            /*
             * Wait until exact button exists.
             */

            const first =
                await until(
                    () =>
                        findProceed(),
                    15000,
                    200
                );


            if (!first) {

                say(
                    'PROCEED button not found.',
                    '#b91c1c'
                );

                return false;
            }


            /*
             * IMPORTANT:
             * Give Angular time to finish recalculating
             * total / reservation fee.
             */

            await wait(1000);


            /*
             * Retry up to 5 times.
             */

            for (
                let attempt = 1;
                attempt <= 5;
                attempt++
            ) {

                const button =
                    findProceed();


                if (!button) {

                    if (
                        nextStepStarted(startUrl, first)
                    ) {

                        say(
                            'next step opened.',
                            '#047857'
                        );

                        return true;
                    }

                    await wait(500);

                    continue;
                }


                /*
                 * Scroll to exact button.
                 */

                try {

                    button.scrollIntoView({
                        behavior: 'instant',
                        block: 'center',
                        inline: 'center'
                    });

                } catch (e) {}

                await wait(250);


                const rect =
                    button.getBoundingClientRect();


                console.log(
                    `[auto-book] PROCEED attempt ${attempt}`,
                    {
                        tag: button.tagName,
                        text: button.innerText,
                        class: button.className,
                        x: rect.left,
                        y: rect.top,
                        width: rect.width,
                        height: rect.height
                    }
                );


                say(
                    `clicking PROCEED (${attempt}/5)…`,
                    '#047857'
                );


                /*
                 * ------------------------------------------------
                 * METHOD 1
                 * Native HTMLElement.click()
                 * ------------------------------------------------
                 */

                try {

                    button.click();

                } catch (e) {

                    console.log(
                        '[auto-book] native click error',
                        e
                    );
                }


                await wait(350);


                /*
                 * ------------------------------------------------
                 * METHOD 2
                 * Full mouse sequence
                 * ------------------------------------------------
                 */

                fire(button);


                await wait(700);


                if (
                    nextStepStarted(startUrl, first)
                ) {

                    say(
                        'PROCEED successful.',
                        '#047857'
                    );

                    return true;
                }


                /*
                 * ------------------------------------------------
                 * METHOD 1B
                 * Keyboard activation (focus + Enter)
                 *
                 * Some Angular Material / CDK buttons only
                 * react to trusted-looking keyboard activation
                 * when pointer-based synthetic events are
                 * ignored.
                 * ------------------------------------------------
                 */

                fireKeyActivate(button);


                await wait(700);


                /*
                 * ------------------------------------------------
                 * Check result
                 * ------------------------------------------------
                 */

                if (
                    nextStepStarted(startUrl, first)
                ) {

                    say(
                        'PROCEED successful.',
                        '#047857'
                    );

                    console.log(
                        '[auto-book] NEXT STEP DETECTED'
                    );

                    return true;
                }


                /*
                 * ------------------------------------------------
                 * METHOD 3
                 *
                 * Sometimes Angular listens to the
                 * actual inner button.
                 * ------------------------------------------------
                 */

                const innerButton =
                    button.querySelector(
                        'button, [role="button"]'
                    );


                if (
                    innerButton &&
                    innerButton !== button
                ) {

                    try {

                        innerButton.click();

                    } catch (e) {}

                    fire(innerButton);

                    await wait(700);


                    if (
                        nextStepStarted(startUrl, first)
                    ) {

                        say(
                            'PROCEED successful.',
                            '#047857'
                        );

                        return true;
                    }
                }


                /*
                 * ------------------------------------------------
                 * METHOD 4
                 *
                 * Click actual element at center point.
                 * ------------------------------------------------
                 */

                const freshRect =
                    button.getBoundingClientRect();


                const centerX =
                    freshRect.left +
                    freshRect.width / 2;

                const centerY =
                    freshRect.top +
                    freshRect.height / 2;


                const under =
                    document.elementFromPoint(
                        centerX,
                        centerY
                    );


                console.log(
                    '[auto-book] element under PROCEED:',
                    under
                );


                if (under) {

                    const clickable =
                        under.closest(
                            'button, [role="button"], a'
                        );


                    if (
                        clickable &&
                        clickable !== button
                    ) {

                        try {
                            clickable.click();
                        } catch (e) {}

                        fire(clickable);

                    } else {

                        try {
                            under.click();
                        } catch (e) {}

                        fire(under);
                    }


                    await wait(900);


                    if (
                        nextStepStarted(startUrl, first)
                    ) {

                        say(
                            'PROCEED successful.',
                            '#047857'
                        );

                        return true;
                    }
                }


                /*
                 * ------------------------------------------------
                 * Wait before next attempt.
                 * ------------------------------------------------
                 */

                console.log(
                    `[auto-book] attempt ${attempt} did not change page`
                );


                await wait(600);
            }


            /*
             * ----------------------------------------------------
             * FAILED
             * ----------------------------------------------------
             */

            say(
                'PROCEED did not open next step.',
                '#b91c1c'
            );


            console.log(
                '[auto-book] PROCEED CLICK FAILED'
            );


            /*
             * DO NOT claim success.
             */

            return false;
        };


    /*
     * ============================================================
     * MAIN
     * ============================================================
     */

    (async () => {

        await until(
            () => document.body,
            30000
        );


        say(
            'looking for the ticket…'
        );


        /*
         * Ticket card
         */

        const card =
            await until(
                findCard,
                40000
            );


        if (!card) {

            say(
                'ticket card not found.',
                '#b91c1c'
            );

            return;
        }


        card.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });


        await wait(600);


        /*
         * BOOK button
         */

        const footer =
            card.querySelector(
                '[id^="ticket_dx_"]'
            );


        const bookButton =
            Array.from(
                (
                    footer ||
                    card
                ).querySelectorAll(
                    'button'
                )
            ).find(
                button =>
                    !button.disabled &&
                    /book/i.test(
                        button.textContent
                    )
            );


        if (!bookButton) {

            say(
                'Book button is disabled or missing.',
                '#b45309'
            );

            return;
        }


        /*
         * Open ticket
         */

        sessionStorage.removeItem(
            storageKey
        );


        say(
            'opening ticket…',
            '#047857'
        );


        try {
            bookButton.click();
        } catch (e) {}

        fire(bookButton);


        /*
         * Wait for panel
         */

        const opened =
            await until(
                () =>
                    /select the tickets for participants/i
                        .test(
                            document.body?.textContent ||
                            ''
                        ) &&
                    document.querySelector(
                        'app-dropdown'
                    ),
                15000
            );


        if (!opened) {

            say(
                'ticket panel did not open.',
                '#b91c1c'
            );

            return;
        }


        /*
         * Quantity / language
         *
         * Hard timeout.
         */

        say(
            'filling quantity and language…',
            '#047857'
        );


        try {

            await Promise.race([

                fillPanel(),

                new Promise(
                    resolve =>
                        setTimeout(
                            resolve,
                            8000
                        )
                )

            ]);

        } catch (e) {

            console.log(
                '[auto-book] fill error',
                e
            );
        }


        /*
         * Time
         */

        if (pending.time) {

            say(
                `selecting time ${pending.time}…`,
                '#047857'
            );


            const timeConfirmed =
                await pickTime();

            if (! timeConfirmed) {

                say(
                    `time ${pending.time} not confirmed — trying PROCEED anyway…`,
                    '#b45309'
                );
            }


            /*
             * Let price / reservation fee update.
             */

            await wait(1500);
        }


        /*
         * Proceed
         */

        const success =
            await clickProceed();


        if (success) {

            /*
             * Stop here.
             *
             * No payment.
             * No checkout.
             */

            console.log(
                '[auto-book] automation stopped after PROCEED.'
            );

        } else {

            console.log(
                '[auto-book] automation stopped because PROCEED was not confirmed.'
            );
        }


        await wait(5000);

        if (banner) {
            banner.remove();
        }

    })();

})();
