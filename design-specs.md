# Especificaciones de Diseño - ProfeGo UI (Figma)

## Paleta de Colores (Tailwind Tokens)

- **Primary / Indigo:** `bg-indigo-600`, hover `bg-indigo-700`, text `text-indigo-600`
- **Gradient Header:** `from-indigo-600 to-purple-600`
- **Backgrounds:** contenedor general `bg-white`, chat body `bg-gray-50`, mensajes de usuario `bg-indigo-50`
- **Borders:** `border-gray-100`, `border-indigo-100`

## Componentes UI Clave

1. **Widget flotante (`#ai-widget`):**
   - Posición fija abajo a la derecha: `fixed bottom-6 right-6 z-50`.
   - Botón toggle con `shadow-lg`, transición de escala `hover:scale-105` e ícono SVG de chat.
2. **Ventana de chat (`#ai-panel`):**
   - Ancho responsivo: `w-80 md:w-96`, esquinas `rounded-2xl` y `shadow-2xl`.
   - Cabecera con degradado, título y botón de cierre.
   - Mensajes con scroll vertical: `overflow-y-auto h-64`.
   - Input y botón de envío con `rounded-xl` y estados de foco índigo.

