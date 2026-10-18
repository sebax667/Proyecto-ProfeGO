<?php
/**
 * Widget Flotante de Asistente de IA
 * 
 * Componente reutilizable para chat con IA
 * Se puede incluir en cualquier vista principal
 */
?>

<div id="ai-chat-widget" class="fixed bottom-6 right-6 z-50 flex flex-col items-end font-sans">
    <!-- Botón flotante para abrir/cerrar widget -->
    <button id="ai-toggle-btn" type="button" aria-controls="ai-panel" aria-expanded="false"
            class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-full p-4 shadow-lg transition-transform hover:scale-105 flex items-center justify-center" 
            aria-label="Abrir asistente de IA"
            title="Asistente de IA">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
        </svg>
    </button>

    <!-- Panel del widget (inicialmente oculto) -->
    <div id="ai-panel" 
         class="hidden max-h-[600px] bg-white w-80 md:w-96 rounded-2xl shadow-2xl border border-gray-100 mb-4 flex flex-col overflow-hidden transition-all duration-300"
         role="dialog" aria-labelledby="ai-title">

        <!-- Encabezado -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-4 text-white flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 bg-white rounded-full animate-pulse"></div>
                <div>
                    <h3 id="ai-title" class="font-bold text-sm flex items-center gap-2">
                        <span>✨</span> ProfeGo SmartMatch
                    </h3>
                    <p class="text-xs text-indigo-100 mt-1">IA buscando tu tutor ideal</p>
                </div>
            </div>
            <button id="ai-close-btn" type="button"
                    class="text-white hover:text-gray-200 text-lg font-bold rounded-full p-1 transition-colors"
                    aria-label="Cerrar widget">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Área de mensajes -->
        <div id="ai-messages" class="p-4 h-64 overflow-y-auto bg-gray-50 flex flex-col gap-3" role="log" aria-live="polite">
            
            <!-- Mensaje de bienvenida -->
            <div class="flex justify-start">
                <div class="bg-white p-3 rounded-xl rounded-tl-none shadow-sm text-sm text-gray-700 max-w-[85%] border border-gray-100">
                    <p class="font-semibold text-indigo-600 mb-1">🤖 Hola</p>
                    <p>Soy tu asistente de IA. Consulta el catálogo de materias disponibles:</p>
                    <ul id="ai-keywords" class="mt-2 text-xs space-y-1" aria-label="Materias disponibles">
                        <li class="text-gray-500">Cargando materias...</li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Área de entrada -->
        <div class="p-3 border-t border-gray-100 bg-white space-y-2">
            
            <!-- Indicador de carga -->
            <div id="ai-loading" class="hidden text-xs text-gray-500 flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <span>El asistente está pensando...</span>
            </div>

            <!-- Input y botón de envío -->
            <div class="flex gap-2 flex-1">
                <input id="ai-input" autocomplete="off"
                       type="text" 
                       placeholder="Ej: Necesito pasar cálculo el viernes..." 
                       class="flex-1 text-sm border border-gray-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 px-3 py-2 outline-none transition-colors"
                       aria-label="Consulta de IA"
                       maxlength="500">
                <button id="ai-send-btn" type="button"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl transition-colors shadow-sm font-medium text-sm active:scale-95"
                        aria-label="Enviar consulta">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </div>

            <!-- Indicador de carácteres -->
            <div class="text-xs text-gray-400">
                <span id="ai-char-count">0</span>/500
            </div>

        </div>

    </div>

</div>

<script src="/assets/js/ai-widget.js" defer></script>
