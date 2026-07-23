(function () {
    'use strict';

    const root = document.querySelector('[data-support-widget]');

    if (!root) {
        return;
    }

    const config = {
        apiUrl: root.dataset.supportApi || '../support/api.php',
        scope: root.dataset.supportScope || 'customer',
        title: root.dataset.supportTitle || 'Support Assistant',
        subtitle: root.dataset.supportSubtitle || 'Database-aware help for guests and staff',
        welcome: root.dataset.supportWelcome || 'Hello. Ask me about rooms, prices, hotel info, or admin dashboard data.',
        hint: root.dataset.supportHint || 'Try: "show available rooms" or "monthly sales this month"',
    };

    const state = {
        open: false,
        sidebar: false,
        loading: false,
        history: [],
    };

    const style = document.createElement('style');
    style.textContent = `
        [data-support-widget] {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 2147483000;
            font-family: inherit;
        }
        .support-launcher {
            width: 56px;
            height: 56px;
            border: 1px solid rgba(212, 175, 55, 0.5);
            border-radius: 999px;
            background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%);
            color: #070A10;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .support-launcher:hover {
            transform: scale(1.08);
            box-shadow: 0 14px 35px rgba(212, 175, 55, 0.4);
        }
        .support-launcher svg {
            width: 26px;
            height: 26px;
        }
        .support-panel {
            position: absolute;
            right: 0;
            bottom: 76px;
            width: min(92vw, 412px);
            height: min(80vh, 700px);
            border-radius: 0 0 18px 18px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-top: 0;
            background: linear-gradient(180deg, rgba(3, 7, 18, 0.98), rgba(2, 6, 23, 1));
            box-shadow: 0 26px 56px rgba(0, 0, 0, 0.55);
            overflow: hidden;
            display: none;
            flex-direction: column;
            color: #f8fafc;
        }
        .support-panel.is-open { display: flex; }
        .support-panel.is-sidebar {
            position: fixed;
            right: 16px;
            top: 0;
            bottom: 0;
            height: 100vh;
            width: min(420px, calc(100vw - 32px));
            border-radius: 0;
            box-shadow: -16px 0 48px rgba(0, 0, 0, 0.6);
        }
        .support-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            flex-wrap: wrap;
            padding: 1rem 1rem 1rem;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(2, 6, 23, 0.98));
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .support-header > div {
            min-width: 0;
            flex: 1 1 auto;
        }
        .support-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .support-subtitle {
            margin: 0.2rem 0 0;
            color: rgba(248, 250, 252, 0.68);
            font-size: 0.88rem;
            line-height: 1.25;
        }
        .support-close {
            border: 0;
            background: rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            width: 36px;
            height: 36px;
            border-radius: 12px;
            font-size: 1.2rem;
            line-height: 1;
        }
        .support-expand {
            border: 0;
            background: rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            width: 36px;
            height: 36px;
            border-radius: 12px;
            font-size: 1rem;
            line-height: 1;
        }
        .support-close:hover,
        .support-expand:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .support-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: grid;
            gap: 0.72rem;
            background:
                radial-gradient(circle at top, rgba(253, 215, 0, 0.05), transparent 30%),
                #030712;
        }
        .support-message {
            max-width: 100%;
            padding: 0.9rem 1rem;
            border-radius: 16px;
            white-space: pre-wrap;
            line-height: 1.45;
            font-size: 0.96rem;
        }
        .support-message--bot {
            background: rgba(17, 24, 39, 0.98);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }
        .support-message--bot .support-message-content {
            display: grid;
            gap: 0.65rem;
        }
        .support-message--user {
            justify-self: end;
            background: linear-gradient(135deg, #fdd700, #ddb400);
            color: #020617;
            box-shadow: 0 10px 18px rgba(253, 215, 0, 0.12);
        }
        .support-message--meta {
            justify-self: center;
            font-size: 0.8rem;
            color: rgba(248, 250, 252, 0.6);
            background: transparent;
        }
        .support-message p {
            margin: 0;
        }
        .support-chips-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
            margin-bottom: 6px;
        }
        .support-chip-btn {
            background: rgba(212, 175, 55, 0.12);
            border: 1px solid rgba(212, 175, 55, 0.35);
            color: #ffdf73;
            border-radius: 99px;
            padding: 4px 10px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .support-chip-btn:hover {
            background: linear-gradient(135deg, #D4AF37, #FFDF73);
            color: #020617;
            transform: translateY(-1px);
        }
        .support-message-table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
        }
        .support-message-table {
            width: 100%;
            min-width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .support-message-table th,
        .support-message-table td {
            padding: 0.55rem 0.7rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            text-align: left;
            vertical-align: top;
        }
        .support-message-table th {
            background: rgba(253, 215, 0, 0.08);
            color: #f8fafc;
            font-weight: 700;
            white-space: nowrap;
        }
        .support-message-table td {
            color: rgba(248, 250, 252, 0.92);
        }
        .support-quick {
            padding: 0 1rem 0.9rem;
            display: flex;
            flex-wrap: nowrap;
            gap: 0.6rem;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.24) transparent;
        }
        .support-quick::-webkit-scrollbar {
            height: 6px;
        }
        .support-quick::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 999px;
        }
        .support-pill {
            flex: 0 0 auto;
            border: 1px solid rgba(248, 250, 252, 0.12);
            background: rgba(15, 23, 42, 0.92);
            color: #f8fafc;
            border-radius: 999px;
            padding: 0.62rem 0.9rem;
            font-size: 0.86rem;
            white-space: nowrap;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.18);
        }
        .support-form {
            padding: 0.95rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(2, 6, 23, 0.98);
        }
        .support-input {
            width: 100%;
            min-height: 88px;
            max-height: 160px;
            resize: vertical;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(15, 23, 42, 0.96);
            color: #f8fafc;
            padding: 1rem 1rem;
            font-size: 0.98rem;
            outline: none;
        }
        .support-input:focus {
            border-color: #fdd700;
            box-shadow: 0 0 0 0.2rem rgba(253, 215, 0, 0.16);
        }
        .support-actions {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 0.8rem;
        }
        .support-hint {
            font-size: 0.82rem;
            color: rgba(248, 250, 252, 0.64);
            line-height: 1.35;
            flex: 1 1 220px;
            min-width: 0;
        }
        .support-send {
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%);
            color: #070A10;
            font-weight: 700;
            padding: 0.78rem 1.1rem;
            min-width: 76px;
            box-shadow: 0 10px 18px rgba(212, 175, 55, 0.2);
            cursor: pointer;
        }
        .support-send:disabled,
        .support-pill:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Mobile & Small Device Optimization (< 768px) */
        @media (max-width: 767.98px) {
            [data-support-widget] {
                right: 12px;
                bottom: 12px;
            }
            [data-support-widget].is-open .support-launcher {
                display: none !important;
            }
            .support-panel.is-open {
                position: fixed !important;
                inset: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                height: 100dvh !important;
                max-height: 100dvh !important;
                border-radius: 0 !important;
                border: none !important;
                z-index: 2147483005 !important;
            }
            .support-expand {
                display: none !important;
            }
            .support-header {
                padding: 12px 16px !important;
            }
            .support-input {
                font-size: 16px !important;
            }
            .support-actions {
                flex-direction: row !important;
                align-items: center !important;
            }
            .support-send {
                width: auto !important;
                padding: 0.6rem 1.2rem !important;
            }
        }
    `;
    document.head.appendChild(style);

    root.innerHTML = `
        <button class="support-launcher" type="button" aria-label="Open support chat">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M12 2C6.5 2 2 6 2 11c0 2.6 1.3 5 3.4 6.6-.1 1-.5 2.6-1.4 4.1-.2.2 0 .5.3.5 2-.4 3.6-1.2 4.5-1.8 1.1.4 2.4.6 3.7.6 5.5 0 10-4 10-9s-4.5-9-10-9z"></path>
            </svg>
        </button>
        <div class="support-panel" role="dialog" aria-label="Support chat">
            <div class="support-header">
                <div>
                    <p class="support-title">${escapeHtml(config.title)}</p>
                    <p class="support-subtitle">${escapeHtml(config.subtitle)}</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="support-expand" type="button" aria-label="Expand support chat to sidebar" aria-pressed="false">⤢</button>
                    <button class="support-close" type="button" aria-label="Close support chat">x</button>
                </div>
            </div>
            <div class="support-messages" aria-live="polite"></div>
            <div class="support-quick"></div>
            <div class="support-form">
                <textarea class="support-input" placeholder="Type your question"></textarea>
                <div class="support-actions">
                    <div class="support-hint">${escapeHtml(config.hint)}</div>
                    <button class="support-send" type="button">Send</button>
                </div>
            </div>
        </div>
    `;

    const launcher = root.querySelector('.support-launcher');
    const panel = root.querySelector('.support-panel');
    const closeButton = root.querySelector('.support-close');
    const expandButton = root.querySelector('.support-expand');
    const messages = root.querySelector('.support-messages');
    const quickRow = root.querySelector('.support-quick');
    const input = root.querySelector('.support-input');
    const sendButton = root.querySelector('.support-send');

    const quickPrompts = config.scope === 'admin'
        ? ['Monthly sales this month', 'Show revenue from today', 'Room occupancy this month', 'Graph data for last 7 days']
        : ['Show available rooms', 'What are the room prices?', 'Tell me about Emperor Hotel', 'Room types and inclusions'];

    const keywordGroups = [
        { label: 'room-availability', terms: ['available rooms', 'room availability', 'rooms available', 'show available rooms', 'which rooms are available'] },
        { label: 'room-types', terms: ['room type', 'room types', 'types of rooms', 'room categories', 'room categories and prices'] },
        { label: 'room-pricing', terms: ['room price', 'room prices', 'room rate', 'room rates', 'price per night', 'pricing by room type'] },
        { label: 'hotel-history', terms: ['hotel history', 'history of emperor', 'founded', 'founding', 'about emperor hotel', 'about the hotel'] },
        { label: 'booking', terms: ['booking', 'reserve', 'reservation', 'check in', 'check out', 'payment', 'pay'] },
        { label: 'sales', terms: ['sales', 'revenue', 'income', 'dashboard', 'graph', 'chart', 'report', 'monthly sales'] },
        { label: 'occupancy', terms: ['occupancy', 'occupied', 'room status', 'room occupancy', 'reservation trend'] },
    ];

    quickPrompts.forEach((prompt) => {
        const button = document.createElement('button');
        button.className = 'support-pill';
        button.type = 'button';
        button.textContent = prompt;
        button.addEventListener('click', () => {
            input.value = prompt;
            sendMessage();
        });
        quickRow.appendChild(button);
    });

    function extractKeywords(text) {
        const normalized = text.toLowerCase();
        const keywords = new Set();

        keywordGroups.forEach((group) => {
            if (group.terms.some((term) => normalized.includes(term))) {
                keywords.add(group.label);
            }
        });

        normalized
            .split(/[^a-z0-9]+/i)
            .map((token) => token.trim())
            .filter((token) => token.length >= 4)
            .slice(0, 8)
            .forEach((token) => keywords.add(token));

        return Array.from(keywords).slice(0, 12);
    }

    function addHistory(role, text) {
        state.history.push({ role, text });
    }

    function buildConversationHistory() {
        return state.history.slice(-10);
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function parseTableLines(lines) {
        const cleanLines = lines.map((line) => line.trim()).filter(Boolean);
        if (cleanLines.length < 2) {
            return null;
        }

        if (!/^\s*\|[\s:-|]+\|\s*$/.test(cleanLines[1])) {
            return null;
        }

        const parseRow = (line) => {
            const cells = line.split('|').map((cell) => cell.trim());
            if (cells[0] === '') {
                cells.shift();
            }
            if (cells[cells.length - 1] === '') {
                cells.pop();
            }
            return cells;
        };

        const headers = parseRow(cleanLines[0]);
        const rows = cleanLines.slice(2).map(parseRow).filter((row) => row.length);

        if (!headers.length) {
            return null;
        }

        return { headers, rows };
    }

    function createTextBlock(text) {
        const paragraph = document.createElement('p');
        paragraph.textContent = text;
        return paragraph;
    }

    function renderMessageContent(text) {
        const normalizedText = String(text).replace(/\r\n/g, '\n').trim();
        const container = document.createElement('div');
        container.className = 'support-message-content';

        // Check if message contains HTML markup (like interactive cards, links, badges)
        if (/<[a-z][\s\S]*>/i.test(normalizedText) || normalizedText.startsWith('<div') || normalizedText.includes('class=')) {
            container.innerHTML = normalizedText;
            return container;
        }

        const lines = normalizedText.split('\n');
        let currentTableLines = [];

        const flushTable = () => {
            if (currentTableLines.length > 0) {
                const table = parseTableLines(currentTableLines);
                if (table) {
                    const wrap = document.createElement('div');
                    wrap.className = 'support-message-table-wrap';

                    const tableElement = document.createElement('table');
                    tableElement.className = 'support-message-table';

                    const thead = document.createElement('thead');
                    const headRow = document.createElement('tr');
                    table.headers.forEach((header) => {
                        const th = document.createElement('th');
                        th.textContent = header;
                        headRow.appendChild(th);
                    });
                    thead.appendChild(headRow);

                    const tbody = document.createElement('tbody');
                    table.rows.forEach((row) => {
                        const tr = document.createElement('tr');
                        table.headers.forEach((_, index) => {
                            const td = document.createElement('td');
                            td.textContent = row[index] ?? '';
                            tr.appendChild(td);
                        });
                        tbody.appendChild(tr);
                    });

                    tableElement.appendChild(thead);
                    tableElement.appendChild(tbody);
                    wrap.appendChild(tableElement);
                    container.appendChild(wrap);
                } else {
                    currentTableLines.forEach((line) => {
                        if (line.trim()) {
                            container.appendChild(createTextBlock(line));
                        }
                    });
                }
                currentTableLines = [];
            }
        };

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const trimmed = line.trim();

            if (trimmed.startsWith('|')) {
                currentTableLines.push(line);
            } else {
                flushTable();
                if (trimmed) {
                    container.appendChild(createTextBlock(line));
                }
            }
        }

        flushTable();

        return container;
    }

    function appendMessage(role, text, chips = []) {
        const node = document.createElement('div');
        node.className = 'support-message support-message--' + role;

        if (role === 'bot') {
            const textStr = String(text).trim();
            if (/<[a-z][\s\S]*>/i.test(textStr) || textStr.startsWith('<div') || textStr.includes('class=')) {
                const contentDiv = document.createElement('div');
                contentDiv.className = 'support-message-content';
                contentDiv.innerHTML = textStr;
                node.appendChild(contentDiv);
            } else {
                node.appendChild(renderMessageContent(textStr));
            }
        } else {
            node.textContent = text;
        }

        messages.appendChild(node);

        if (chips && Array.isArray(chips) && chips.length > 0) {
            const chipsWrap = document.createElement('div');
            chipsWrap.className = 'support-chips-wrap';
            chips.forEach((chipText) => {
                const btn = document.createElement('button');
                btn.className = 'support-chip-btn';
                btn.textContent = chipText;
                btn.type = 'button';
                btn.onclick = () => {
                    input.value = chipText.replace(/^[^\w\s]+/, '').trim();
                    sendMessage();
                };
                chipsWrap.appendChild(btn);
            });
            messages.appendChild(chipsWrap);
        }

        messages.scrollTop = messages.scrollHeight;
        return node;
    }

    function openPanel() {
        state.open = true;
        root.classList.add('is-open');
        panel.classList.add('is-open');
        panel.classList.toggle('is-sidebar', state.sidebar);
        expandButton.setAttribute('aria-pressed', state.sidebar ? 'true' : 'false');
        expandButton.textContent = state.sidebar ? '⇤' : '⤢';
        expandButton.setAttribute('aria-label', state.sidebar ? 'Collapse support chat from sidebar' : 'Expand support chat to sidebar');
        input.focus();
    }

    function closePanel() {
        state.open = false;
        root.classList.remove('is-open');
        panel.classList.remove('is-open');
    }

    function toggleSidebar() {
        state.sidebar = !state.sidebar;
        panel.classList.toggle('is-sidebar', state.sidebar);
        expandButton.setAttribute('aria-pressed', state.sidebar ? 'true' : 'false');
        expandButton.textContent = state.sidebar ? '⇤' : '⤢';
        expandButton.setAttribute('aria-label', state.sidebar ? 'Collapse support chat from sidebar' : 'Expand support chat to sidebar');
    }

    async function sendMessage() {
        const message = input.value.trim();

        if (!message || state.loading) {
            return;
        }

        appendMessage('user', message);
        addHistory('user', message);
        input.value = '';
        state.loading = true;
        sendButton.disabled = true;
        const keywords = extractKeywords(message);

        try {
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message,
                    scope: config.scope,
                    keywords,
                    history: buildConversationHistory(),
                }),
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                appendMessage('meta', payload.error || 'Support request failed.');
                return;
            }

            appendMessage('bot', payload.reply, payload.quick_chips || []);
            addHistory('assistant', payload.reply);
        } catch (error) {
            appendMessage('meta', 'Network error while reaching support.');
        } finally {
            state.loading = false;
            sendButton.disabled = false;
        }
    }

    launcher.addEventListener('click', () => {
        if (state.open) {
            closePanel();
            return;
        }

        openPanel();
    });

    closeButton.addEventListener('click', closePanel);
    expandButton.addEventListener('click', () => {
        toggleSidebar();
        if (state.open) {
            panel.classList.toggle('is-open', true);
        }
    });
    sendButton.addEventListener('click', sendMessage);

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    appendMessage('bot', config.welcome);
})();
