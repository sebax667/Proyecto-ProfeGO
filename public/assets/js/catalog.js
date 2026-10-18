import { api } from '/assets/js/app.js';

const bookingDialog = document.getElementById('booking-dialog');
const bookingForm = document.getElementById('booking-form');
const bookingTutorName = document.getElementById('booking-tutor-name');
const bookingFeedback = document.getElementById('booking-feedback');

if (bookingDialog instanceof HTMLDialogElement && bookingForm instanceof HTMLFormElement) {
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const bookButton = event.target.closest('[data-action="book"]');
        if (bookButton instanceof HTMLButtonElement) {
            bookingForm.elements.tutor_id.value = bookButton.dataset.tutorId ?? '';
            bookingTutorName.textContent = `Tutor: ${bookButton.dataset.tutorName ?? ''}`;
            bookingFeedback.hidden = true;
            bookingFeedback.textContent = '';
            bookingDialog.showModal();
            bookingForm.elements.starts_at.focus();
            return;
        }

        if (event.target.closest('[data-action="close-booking"]')) {
            bookingDialog.close();
        }
    });

    bookingForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        bookingFeedback.hidden = true;

        if (!bookingForm.reportValidity()) {
            return;
        }

        const tutorId = Number(bookingForm.elements.tutor_id.value);
        const startsAt = new Date(bookingForm.elements.starts_at.value);
        const endsAt = new Date(bookingForm.elements.ends_at.value);
        if (!Number.isSafeInteger(tutorId) || tutorId <= 0 || Number.isNaN(startsAt.getTime()) || Number.isNaN(endsAt.getTime())) {
            bookingFeedback.textContent = 'Selecciona un tutor y un horario válido.';
            bookingFeedback.hidden = false;
            return;
        }
        if (endsAt <= startsAt) {
            bookingFeedback.textContent = 'La hora de fin debe ser posterior a la hora de inicio.';
            bookingFeedback.hidden = false;
            return;
        }

        const submitButton = bookingForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        try {
            const response = await api('/api/bookings', {
                method: 'POST',
                body: JSON.stringify({
                    tutor_id: tutorId,
                    starts_at: startsAt.toISOString(),
                    ends_at: endsAt.toISOString(),
                }),
            });
            if (response.status !== 'success') {
                throw new Error(response.message || 'No se pudo solicitar la sesión.');
            }

            bookingFeedback.textContent = response.message || 'Solicitud enviada correctamente.';
            bookingFeedback.hidden = false;
            bookingForm.reset();
        } catch (error) {
            bookingFeedback.textContent = error instanceof Error
                ? error.message
                : 'No se pudo solicitar la sesión.';
            bookingFeedback.hidden = false;
        } finally {
            submitButton.disabled = false;
        }
    });
}
