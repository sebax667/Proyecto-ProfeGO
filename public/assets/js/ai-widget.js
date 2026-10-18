document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('ai-chat-widget');
    if (!root) {
        return;
    }

    const toggleButton = root.querySelector('#ai-toggle-btn');
    const panel = root.querySelector('#ai-panel');
    const closeButton = root.querySelector('#ai-close-btn');
    const messages = root.querySelector('#ai-messages');
    const input = root.querySelector('#ai-input');
    const sendButton = root.querySelector('#ai-send-btn');
    const loading = root.querySelector('#ai-loading');
    const characterCount = root.querySelector('#ai-char-count');
    const keywordsList = root.querySelector('#ai-keywords');
    let isLoading = false;

    if (
        !(toggleButton instanceof HTMLButtonElement)
        || !(panel instanceof HTMLDivElement)
        || !(closeButton instanceof HTMLButtonElement)
        || !(messages instanceof HTMLDivElement)
        || !(input instanceof HTMLInputElement)
        || !(sendButton instanceof HTMLButtonElement)
        || !(loading instanceof HTMLDivElement)
        || !(characterCount instanceof HTMLSpanElement)
        || !(keywordsList instanceof HTMLUListElement)
    ) {
        return;
    }

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);

    const appendUserMessage = (text) => {
        const message = document.createElement('div');
        message.className = 'flex justify-end';
        message.innerHTML = `<div class="bg-indigo-50 text-gray-700 border border-indigo-100 rounded-xl rounded-tr-none px-3 py-3 max-w-[85%] text-sm leading-relaxed break-words">${escapeHtml(text)}</div>`;
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;
    };

    const appendAssistantMessage = (recommendation) => {
        const message = document.createElement('div');
        message.className = 'flex justify-start';
        let content = `<p class="text-gray-800 mb-2">${escapeHtml(recommendation.message)}</p>`;

        if (Array.isArray(recommendation.tutors) && recommendation.tutors.length > 0) {
            content += '<div class="space-y-2 mt-3">';
            for (const tutor of recommendation.tutors) {
                content += `<div class="bg-indigo-50 border border-indigo-100 rounded-xl p-2 text-xs">
                    <div class="font-semibold text-indigo-600">${escapeHtml(tutor.name)}</div>
                    <div class="text-gray-600">${escapeHtml(tutor.specialty)}</div>
                    <div class="flex justify-between mt-1 text-gray-500">
                        <span>⭐ ${escapeHtml(tutor.rating)}</span>
                        <span>$${escapeHtml(tutor.hourly_rate)}/hora</span>
                    </div>
                </div>`;
            }
            content += '</div>';
        }

        message.innerHTML = `<div class="bg-white p-3 rounded-xl rounded-tl-none shadow-sm text-sm text-gray-700 max-w-[85%] border border-gray-100">${content}</div>`;
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;
    };

    const loadKeywords = async () => {
        try {
            const response = await fetch('/api/ai/keywords', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            const keywords = Array.isArray(payload.keywords) ? payload.keywords : [];
            keywordsList.replaceChildren();
            if (keywords.length === 0) {
                const item = document.createElement('li');
                item.className = 'text-gray-500';
                item.textContent = 'No hay materias disponibles.';
                keywordsList.appendChild(item);
                return;
            }

            for (const keyword of keywords) {
                const item = document.createElement('li');
                item.textContent = `• ${String(keyword)}`;
                keywordsList.appendChild(item);
            }
        } catch (error) {
            console.error('No se pudieron cargar las materias del asistente:', error);
            const item = document.createElement('li');
            item.className = 'text-red-500';
            item.textContent = 'No se pudieron cargar las materias.';
            keywordsList.replaceChildren(item);
        }
    };

    const sendQuery = async () => {
        const query = input.value.trim();
        if (query.length < 3) {
            appendAssistantMessage({
                message: query ? 'La consulta debe tener al menos 3 caracteres.' : 'Por favor escribe una consulta.',
                tutors: [],
            });
            return;
        }

        isLoading = true;
        sendButton.disabled = true;
        input.disabled = true;
        loading.classList.remove('hidden');
        appendUserMessage(query);

        try {
            const response = await fetch('/api/ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ query }),
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.message || `HTTP ${response.status}`);
            }

            appendAssistantMessage(payload.status === 'success' && payload.data
                ? payload.data
                : { message: payload.message || 'No pude procesar tu consulta.', tutors: [] });
            input.value = '';
            characterCount.textContent = '0';
        } catch (error) {
            console.error('Error en la consulta de IA:', error);
            appendAssistantMessage({
                message: error instanceof Error ? error.message : 'Error al conectar con el asistente.',
                tutors: [],
            });
        } finally {
            isLoading = false;
            sendButton.disabled = false;
            input.disabled = false;
            loading.classList.add('hidden');
            input.focus();
        }
    };

    toggleButton.addEventListener('click', () => {
        const isOpen = panel.classList.toggle('hidden') === false;
        toggleButton.setAttribute('aria-expanded', String(isOpen));
        if (isOpen) {
            input.focus();
        }
    });
    closeButton.addEventListener('click', () => {
        panel.classList.add('hidden');
        toggleButton.setAttribute('aria-expanded', 'false');
    });
    input.addEventListener('input', () => {
        characterCount.textContent = String(input.value.length);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !isLoading) {
            void sendQuery();
        }
    });
    sendButton.addEventListener('click', () => {
        if (!isLoading) {
            void sendQuery();
        }
    });

    void loadKeywords();
});
