import { appState, getRegionById, MARS_REGIONS } from '../../core/state.js';
import { validateTripForm } from '../../utils/validators.js';
import { escapeHtml } from '../../utils/helpers.js';
import { showPriorityConfirmSwal } from '../../utils/sweetalerts.js';
import { travelService } from './travel-service.js';
import { formatDistance } from '../../utils/formatters.js';

export function renderTripForm(container, { editingTrip, prefillDestinationId, onSaved, onCancel, onPickOnMap }) {
  const isEditing = Boolean(editingTrip);
  const defaultDeparture = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);
  const defaultArrival = new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10);

  const initial = editingTrip || {
    name: '',
    originId: 'elysium',
    destinationId: prefillDestinationId || 'jezero',
    departureDate: defaultDeparture,
    arrivalDate: defaultArrival,
    participants: 4,
    missionType: 'Científica',
    objective: '',
    riskTolerance: 'baixo',
    notes: '',
    waypoints: [],
  };

  const destRegion = getRegionById(initial.destinationId);

  container.innerHTML = `
    <div class="panel">
      <div class="panel__header">
        <div>
          <h2 class="panel__title">${isEditing ? 'Editar Missão' : 'Criar Nova Viagem em Marte'}</h2>
          <p class="panel__meta">Preencha os parâmetros operacionais da expedição ou selecione o destino no globo 3D.</p>
        </div>
        <button type="button" class="btn btn--secondary btn--sm" id="btn-pick-dest-3d">
          Selecionar Destino no Mapa 3D
        </button>
      </div>

      <form id="trip-planner-form" novalidate>
        <div class="planner-form-grid">
          <div class="field">
            <label class="field__label" for="trip-name">Nome da Missão</label>
            <input id="trip-name" name="name" type="text" class="field__control" value="${escapeHtml(initial.name)}" placeholder="Ex: Expedição Horizonte Oxia" required />
            <div class="field__error" data-error-for="name"></div>
          </div>

          <div class="field">
            <label class="field__label" for="trip-type">Tipo de Missão</label>
            <select id="trip-type" name="missionType" class="field__control">
              ${['Científica', 'Reconhecimento', 'Colonização', 'Resgate', 'Mineração']
                .map((type) => `<option value="${type}" ${initial.missionType === type ? 'selected' : ''}>${type}</option>`)
                .join('')}
            </select>
            <div class="field__error" data-error-for="missionType"></div>
          </div>

          <div class="field">
            <label class="field__label" for="trip-origin">Local de Partida</label>
            <select id="trip-origin" name="originId" class="field__control">
              ${MARS_REGIONS.map(
                (r) => `<option value="${r.id}" ${initial.originId === r.id ? 'selected' : ''}>${escapeHtml(r.name)} (${r.category === 'recommended' ? 'Segura' : 'Risco'})</option>`
              ).join('')}
            </select>
            <div class="field__error" data-error-for="originId"></div>
          </div>

          <div class="field">
            <label class="field__label" for="trip-destination">Destino em Marte</label>
            <select id="trip-destination" name="destinationId" class="field__control">
              ${MARS_REGIONS.map(
                (r) => `<option value="${r.id}" ${initial.destinationId === r.id ? 'selected' : ''}>${escapeHtml(r.name)} (${r.category === 'recommended' ? 'Segura' : 'Risco'})</option>`
              ).join('')}
            </select>
            <div class="field__error" data-error-for="destinationId"></div>
          </div>

          <div class="field">
            <label class="field__label" for="trip-departure">Data de Partida</label>
            <input id="trip-departure" name="departureDate" type="date" class="field__control" value="${escapeHtml(initial.departureDate)}" required />
            <div class="field__error" data-error-for="departureDate"></div>
          </div>

          <div class="field">
            <label class="field__label" for="trip-arrival">Data Prevista de Chegada</label>
            <input id="trip-arrival" name="arrivalDate" type="date" class="field__control" value="${escapeHtml(initial.arrivalDate)}" required />
            <div class="field__error" data-error-for="arrivalDate"></div>
          </div>

          <div class="field">
            <label class="field__label" for="trip-participants">Número de Participantes</label>
            <input id="trip-participants" name="participants" type="number" min="1" max="50" class="field__control tabular-nums" value="${escapeHtml(initial.participants)}" required />
            <div class="field__error" data-error-for="participants"></div>
          </div>

          <div class="field">
            <label class="field__label" for="trip-risk">Nível de Risco Permitido</label>
            <select id="trip-risk" name="riskTolerance" class="field__control">
              <option value="baixo" ${initial.riskTolerance === 'baixo' ? 'selected' : ''}>Baixo (Apenas zonas recomendadas)</option>
              <option value="moderado" ${initial.riskTolerance === 'moderado' ? 'selected' : ''}>Moderado (Inclui terreno misto)</option>
              <option value="elevado" ${initial.riskTolerance === 'elevado' ? 'selected' : ''}>Elevado (Missões críticas)</option>
            </select>
            <div class="field__error" data-error-for="riskTolerance"></div>
          </div>

          <div class="field planner-form__full">
            <label class="field__label" for="trip-objective">Objetivo da Exploração</label>
            <input id="trip-objective" name="objective" type="text" class="field__control" value="${escapeHtml(initial.objective)}" placeholder="Descreva o propósito científico ou logístico da missão" required />
            <div class="field__error" data-error-for="objective"></div>
          </div>

          <div class="field planner-form__full">
            <label class="field__label" for="trip-notes">Observações Adicionais</label>
            <textarea id="trip-notes" name="notes" class="field__control" placeholder="Requisitos de equipamento, janelas de comunicação ou restrições térmicas...">${escapeHtml(initial.notes || '')}</textarea>
          </div>
        </div>

        <div class="meta-line" id="form-route-preview" style="margin-top: var(--space-3);">
          <span>Destino selecionado: <strong>${escapeHtml(destRegion.name)}</strong></span>
          <span class="meta-line__sep" aria-hidden="true">·</span>
          <span>${escapeHtml(destRegion.safetyScore)}</span>
        </div>

        <div class="planner-form__actions">
          <button type="button" class="btn btn--ghost" id="btn-cancel-form">Cancelar</button>
          <button type="submit" class="btn btn--primary">${isEditing ? 'Guardar Alterações' : 'Guardar Viagem'}</button>
        </div>
      </form>
    </div>
  `;

  const formEl = container.querySelector('#trip-planner-form');
  const originEl = container.querySelector('#trip-origin');
  const destEl = container.querySelector('#trip-destination');
  const previewEl = container.querySelector('#form-route-preview');

  function refreshPreview() {
    const summary = travelService.computeRouteSummary(originEl.value, destEl.value, initial.waypoints || []);
    const dest = getRegionById(destEl.value);
    previewEl.innerHTML = `
      <span>Distância estimada: <strong class="tabular-nums">${escapeHtml(formatDistance(summary.distanceKm, appState.settings.distanceUnit))}</strong></span>
      <span class="meta-line__sep" aria-hidden="true">·</span>
      <span>Duração: <strong class="tabular-nums">${summary.estimatedDays} Sóis</strong></span>
      <span class="meta-line__sep" aria-hidden="true">·</span>
      <span class="${dest.category === 'recommended' ? 'status-text status-text--safe' : 'status-text status-text--danger'}">${escapeHtml(dest.safetyScore)}</span>
    `;
  }

  originEl?.addEventListener('change', refreshPreview);
  destEl?.addEventListener('change', refreshPreview);
  refreshPreview();

  container.querySelector('#btn-cancel-form')?.addEventListener('click', onCancel);
  container.querySelector('#btn-pick-dest-3d')?.addEventListener('click', () => {
    onPickOnMap?.(originEl.value, destEl.value);
  });

  formEl?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
      name: formEl.name.value.trim(),
      originId: formEl.originId.value,
      destinationId: formEl.destinationId.value,
      departureDate: formEl.departureDate.value,
      arrivalDate: formEl.arrivalDate.value,
      participants: Number(formEl.participants.value),
      missionType: formEl.missionType.value,
      objective: formEl.objective.value.trim(),
      riskTolerance: formEl.riskTolerance.value,
      notes: formEl.notes.value.trim(),
      waypoints: initial.waypoints || [],
    };

    const { isValid, errors } = validateTripForm(data);

    formEl.querySelectorAll('[data-error-for]').forEach((el) => {
      const key = el.dataset.errorFor;
      el.textContent = errors[key] || '';
    });
    formEl.querySelectorAll('.field__control').forEach((ctrl) => {
      ctrl.classList.toggle('is-invalid', Boolean(errors[ctrl.name]));
    });

    if (!isValid) return;

    const summary = travelService.computeRouteSummary(data.originId, data.destinationId, data.waypoints);
    if (summary.hasRiskZones && data.riskTolerance !== 'elevado') {
      const hasCritical = summary.riskRegions.some((r) => r.riskLevel === 'critico');
      const confirmed = await showPriorityConfirmSwal({
        priority: hasCritical ? 'P1' : 'P2',
        title: hasCritical ? 'Emergência: Zona Proibida na Rota' : 'Alerta de Rota em Zona de Risco',
        text: `A missão atravessa ${summary.riskRegions.map((r) => r.name).join(', ')} com tolerância definida como "${data.riskTolerance}". Deseja confirmar o planeamento mesmo assim?`,
        confirmText: 'Confirmar Mesmo Assim',
        cancelText: 'Rever Destino',
      });
      if (!confirmed) return;
    }

    const saved = travelService.saveTrip(data, editingTrip?.id || null);
    onSaved?.(saved);
  });
}
