import { appState, getRegionById, MARS_REGIONS, notifyIfEnabled, logActivity } from '../../core/state.js';
import { getSharedMarsGlobe, setMarsStageMode } from '../home/mars-globe.js';
import { travelService } from './travel-service.js';
import { formatCoordinates, formatDistance, formatTemperature } from '../../utils/formatters.js';
import { escapeHtml } from '../../utils/helpers.js';
import { showPriorityToastSwal } from '../../utils/sweetalerts.js';
import { showPriorityConfirmSwal } from '../../utils/sweetalerts.js';
import { storage, STORAGE_KEYS } from '../../storage.js';
import { backendApi } from '../../core/backend-api.js';
import { refreshBackendData } from '../../core/backend-integration.js';

export function createTravelMapView({ onCreateTripWithRoute }) {
  let originId = 'elysium';
  let destinationId = 'jezero';
  let waypoints = ['gale'];
  let activeTripId = null;
  let apiRoute = null;
  let apiBusy = false;
  let apiError = '';

  function updateGlobeRoute() {
    const globe = getSharedMarsGlobe();
    if (!globe) return;
    const ordered = [originId, ...waypoints, destinationId].filter(Boolean);
    globe.setRoute(ordered);
    globe.setSelectedRegion(destinationId, true);
  }

  function renderPanel(container) {
    const summary = travelService.computeRouteSummary(originId, destinationId, waypoints);
    const dest = getRegionById(destinationId);
    const origin = getRegionById(originId);
    const distUnit = appState.settings.distanceUnit;
    const tempUnit = appState.settings.temperatureUnit;

    const summaryEl = container.querySelector('#route-summary-panel');
    if (!summaryEl) return;

    summaryEl.innerHTML = `
      <div class="panel__header">
        <div>
          <h2 class="panel__title">Planeamento de Rota 3D</h2>
          <p class="panel__meta">Selecione origem, destino, pontos de passagem e clique no globo para inspecionar regiões.</p>
        </div>
      </div>

      <div class="planner-form-grid" style="gap: var(--space-3);">
        <div class="field">
          <label class="field__label" for="route-origin">Origem</label>
          <select id="route-origin" class="field__control">
            ${MARS_REGIONS.map(
              (r) => `<option value="${r.id}" ${originId === r.id ? 'selected' : ''}>${escapeHtml(r.name)}</option>`
            ).join('')}
          </select>
        </div>

        <div class="field">
          <label class="field__label" for="route-destination">Destino</label>
          <select id="route-destination" class="field__control">
            ${MARS_REGIONS.map(
              (r) => `<option value="${r.id}" ${destinationId === r.id ? 'selected' : ''}>${escapeHtml(r.name)}</option>`
            ).join('')}
          </select>
        </div>
      </div>

      <div style="margin-top: var(--space-4);">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
          <span class="field__label">Pontos de Passagem (${waypoints.length})</span>
          <div style="display: flex; gap: 6px;">
            <select id="route-add-wp-select" class="field__control" style="min-height: 36px; padding: 4px 8px; font-size: 0.82rem;">
              ${MARS_REGIONS.filter((r) => r.id !== originId && r.id !== destinationId && !waypoints.includes(r.id))
                .map((r) => `<option value="${r.id}">${escapeHtml(r.name)}</option>`)
                .join('')}
            </select>
            <button type="button" class="btn btn--secondary btn--sm" id="btn-add-waypoint">Adicionar</button>
          </div>
        </div>

        <div class="waypoints-list">
          ${
            waypoints.length === 0
              ? '<div class="panel__meta">Rota direta sem pontos de passagem intermédios.</div>'
              : waypoints
                  .map((wpId, idx) => {
                    const reg = getRegionById(wpId);
                    return `
                      <div class="waypoint-row">
                        <span>${idx + 1}. ${escapeHtml(reg.name)} · <span class="tabular-nums">${escapeHtml(formatCoordinates(reg.lat, reg.lon))}</span></span>
                        <div class="waypoint-row__controls">
                          <button type="button" class="btn btn--ghost btn--sm" data-wp-up="${idx}" ${idx === 0 ? 'disabled' : ''} aria-label="Mover para cima">↑</button>
                          <button type="button" class="btn btn--ghost btn--sm" data-wp-down="${idx}" ${idx === waypoints.length - 1 ? 'disabled' : ''} aria-label="Mover para baixo">↓</button>
                          <button type="button" class="btn btn--danger btn--sm" data-wp-del="${idx}" aria-label="Remover ponto">×</button>
                        </div>
                      </div>
                    `;
                  })
                  .join('')
          }
        </div>
      </div>

      <div class="route-summary">
        <div class="metric-cell">
          <div class="metric-cell__label">Distância Estimada</div>
          <div class="metric-cell__value tabular-nums">${escapeHtml(formatDistance(summary.distanceKm, distUnit))}</div>
        </div>
        <div class="metric-cell">
          <div class="metric-cell__label">Duração Estimada</div>
          <div class="metric-cell__value tabular-nums">${summary.estimatedDays} Sóis (${summary.estimatedHours} h)</div>
        </div>
        <div class="metric-cell">
          <div class="metric-cell__label">Temperatura Destino</div>
          <div class="metric-cell__value tabular-nums">${escapeHtml(formatTemperature(dest.tempCelsius, tempUnit))}</div>
        </div>
        <div class="metric-cell">
          <div class="metric-cell__label">Zonas de Risco na Rota</div>
          <div class="metric-cell__value ${summary.hasRiskZones ? 'status-text--danger' : 'status-text--safe'}">
            ${summary.hasRiskZones ? `▲ ${summary.riskRegions.length} zona(s) de demonstração` : '● Sem zonas de demonstração'}
          </div>
        </div>
      </div>

      ${
        summary.hasRiskZones
          ? `<p class="panel__meta" style="margin-top: var(--space-3); color: #ff8a8a;">
              Aviso demonstrativo: esta trajetória passa por ${summary.riskRegions.map((r) => r.name).join(', ')}. Não é uma avaliação de segurança.
            </p>`
          : ''
      }

      <div class="planner-form__actions">
        ${
          activeTripId
            ? `<button type="button" class="btn btn--secondary" id="btn-save-route-to-trip">Atualizar Rota da Viagem</button>`
            : ''
        }
        <button type="button" class="btn btn--primary" id="btn-use-route-new-trip">
          Criar Viagem com esta Rota
        </button>
      </div>

      <section class="panel" style="margin-top: var(--space-4);" aria-label="Planeamento de rota no servidor">
        <div class="panel__header">
          <div>
            <h2 class="panel__title">Planeador do servidor</h2>
            <p class="panel__meta">Gera alternativas com dados importados e o assistente configurado no backend.</p>
          </div>
          <button type="button" class="btn btn--secondary btn--sm" id="btn-plan-server-route" ${!appState.backendUser || apiBusy ? 'disabled' : ''}>
            ${apiBusy ? 'A calcular…' : 'Calcular rota com IA'}
          </button>
        </div>
        ${
          !appState.backendUser
            ? '<p class="panel__meta">Inicie sessão em Definições → Conta e ligação para usar o planeador remoto.</p>'
            : ''
        }
        ${apiError ? `<p class="field__error" role="alert">${escapeHtml(apiError)}</p>` : ''}
        ${
          apiRoute
            ? `
              <p class="panel__meta">Cobertura: ${apiRoute.data_coverage.hazard_records_loaded} perigos e ${apiRoute.data_coverage.terrain_cells_loaded} células de terreno. Cobertura incompleta não significa ausência de risco.</p>
              <div class="waypoints-list">
                ${apiRoute.candidates.map((candidate, index) => {
                  const crossed = candidate.hazards_crossed || [];
                  const isSelected = apiRoute.selected_index === index;
                  return `
                    <article class="waypoint-row" style="align-items: flex-start;">
                      <div>
                        <strong>${escapeHtml(candidate.name)}</strong>
                        <p class="panel__meta">${escapeHtml(candidate.rationale)}</p>
                        <p class="panel__meta">
                          ${escapeHtml(formatDistance(candidate.distance_km, distUnit))} ·
                          Adequação ${Number(candidate.score).toFixed(0)}/100 ·
                          risco estimado: ${escapeHtml(candidate.risk_level)}
                        </p>
                        ${crossed.length ? `<p class="field__error">Cruza ${crossed.length} perigo(s) cartografado(s); requer confirmação explícita.</p>` : ''}
                        ${candidate.steep_terrain_cells ? `<p class="panel__meta">Células com declive ≥ 15°: ${candidate.steep_terrain_cells}.</p>` : ''}
                        <button type="button" class="btn ${isSelected ? 'btn--secondary' : 'btn--primary'} btn--sm" data-select-server-route="${index}" ${isSelected || apiBusy ? 'disabled' : ''}>
                          ${isSelected ? 'Rota ativa' : 'Selecionar rota'}
                        </button>
                      </div>
                    </article>
                  `;
                }).join('')}
              </div>
            `
            : ''
        }
      </section>
    `;

    summaryEl.querySelector('#route-origin')?.addEventListener('change', (e) => {
      originId = e.target.value;
      waypoints = waypoints.filter((w) => w !== originId && w !== destinationId);
      apiRoute = null;
      apiError = '';
      updateGlobeRoute();
      renderPanel(container);
    });

    summaryEl.querySelector('#route-destination')?.addEventListener('change', (e) => {
      destinationId = e.target.value;
      waypoints = waypoints.filter((w) => w !== originId && w !== destinationId);
      apiRoute = null;
      apiError = '';
      updateGlobeRoute();
      renderPanel(container);
      const reg = getRegionById(destinationId);
      if (reg.category === 'dangerous' && appState.settings.notifications?.riskAlerts) {
        showPriorityToastSwal({
          priority: reg.riskLevel === 'critico' ? 'P1' : 'P2',
          title: `Destino de Risco: ${reg.name}`,
          message: reg.conditions,
        });
      }
    });

    summaryEl.querySelector('#btn-add-waypoint')?.addEventListener('click', () => {
      const sel = summaryEl.querySelector('#route-add-wp-select');
      if (sel && sel.value) {
        waypoints.push(sel.value);
        updateGlobeRoute();
        renderPanel(container);
        const reg = getRegionById(sel.value);
        if (reg.category === 'dangerous' && appState.settings.notifications?.riskAlerts) {
          showPriorityToastSwal({
            priority: reg.riskLevel === 'critico' ? 'P1' : 'P2',
            title: `Passagem em Zona Perigosa: ${reg.name}`,
            message: reg.conditions,
          });
        }
      }
    });

    summaryEl.querySelectorAll('[data-wp-up]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const idx = Number(btn.dataset.wpUp);
        if (idx > 0) {
          [waypoints[idx - 1], waypoints[idx]] = [waypoints[idx], waypoints[idx - 1]];
          updateGlobeRoute();
          renderPanel(container);
        }
      });
    });

    summaryEl.querySelectorAll('[data-wp-down]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const idx = Number(btn.dataset.wpDown);
        if (idx < waypoints.length - 1) {
          [waypoints[idx + 1], waypoints[idx]] = [waypoints[idx], waypoints[idx + 1]];
          updateGlobeRoute();
          renderPanel(container);
        }
      });
    });

    summaryEl.querySelectorAll('[data-wp-del]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const idx = Number(btn.dataset.wpDel);
        waypoints.splice(idx, 1);
        updateGlobeRoute();
        renderPanel(container);
      });
    });

    summaryEl.querySelector('#btn-save-route-to-trip')?.addEventListener('click', () => {
      const trip = travelService.getById(activeTripId);
      if (trip) {
        trip.originId = originId;
        trip.destinationId = destinationId;
        trip.waypoints = [...waypoints];
        trip.distanceKm = summary.distanceKm;
        trip.estimatedDays = summary.estimatedDays;
        storage.set(STORAGE_KEYS.TRIPS, appState.trips);
        logActivity(`Rota atualizada na missão: ${trip.name}`);
        notifyIfEnabled('routeChanges', {
          title: 'Alteração de Rota Guardada',
          message: `Nova trajetória calculada (${formatDistance(summary.distanceKm, distUnit)}).`,
          variant: 'safe',
        });
      }
    });

    summaryEl.querySelector('#btn-use-route-new-trip')?.addEventListener('click', () => {
      onCreateTripWithRoute?.({ originId, destinationId, waypoints: [...waypoints] });
    });

    summaryEl.querySelector('#btn-plan-server-route')?.addEventListener('click', async () => {
      if (!appState.backendUser || apiBusy) return;
      apiBusy = true;
      apiError = '';
      renderPanel(container);
      try {
        apiRoute = await backendApi.planRoute(
          { latitude: origin.lat, longitude: origin.lon },
          { latitude: dest.lat, longitude: dest.lon },
        );
      } catch (error) {
        apiError = error.message;
      } finally {
        apiBusy = false;
        renderPanel(container);
      }
    });

    summaryEl.querySelectorAll('[data-select-server-route]').forEach((button) => {
      button.addEventListener('click', async () => {
        const candidateIndex = Number(button.dataset.selectServerRoute);
        const candidate = apiRoute?.candidates[candidateIndex];
        if (!apiRoute || !candidate || apiBusy) return;

        const hasCrossedHazards = (candidate.hazards_crossed || []).length > 0;
        if (hasCrossedHazards) {
          const confirmed = await showPriorityConfirmSwal({
            priority: 'P1',
            title: 'Rota cruza perigos cartografados',
            text: 'A seleção será registada com confirmação explícita. Os dados são incompletos e não certificam segurança.',
            confirmText: 'Confirmar risco e selecionar',
            cancelText: 'Escolher outra rota',
          });
          if (!confirmed) return;
        }

        apiBusy = true;
        apiError = '';
        renderPanel(container);
        try {
          apiRoute = await backendApi.selectRoute(apiRoute.id, candidateIndex, hasCrossedHazards);
          appState.activeBackendRoute = apiRoute;
          await refreshBackendData();
        } catch (error) {
          apiError = error.message;
        } finally {
          apiBusy = false;
          renderPanel(container);
        }
      });
    });
  }

  return {
    mount(container, initialRoute = {}) {
      originId = initialRoute.originId || 'elysium';
      destinationId = initialRoute.destinationId || appState.selectedRegionId || 'jezero';
      if (originId === destinationId) {
        originId = destinationId === 'elysium' ? 'oxia' : 'elysium';
      }
      waypoints = Array.isArray(initialRoute.waypoints) ? [...initialRoute.waypoints] : [];
      activeTripId = initialRoute.tripId || null;
      apiRoute = null;
      apiBusy = false;
      apiError = '';

      setMarsStageMode('planner-routes', appState.settings);

      container.innerHTML = `
        <div class="route-planner-layout">
          <div class="route-globe-spacer">
            <div class="route-globe-spacer__hud">
              <div class="route-legend">
                <span class="legend-item"><span class="legend-swatch legend-swatch--safe"></span>Recomendada</span>
                <span class="legend-item"><span class="legend-swatch legend-swatch--danger"></span>Perigosa</span>
              </div>
              <button type="button" class="btn btn--secondary btn--sm" id="route-globe-reset">Repor Vista</button>
            </div>
          </div>

          <div class="panel" id="route-summary-panel"></div>
        </div>
      `;

      const globe = getSharedMarsGlobe();
      if (globe) {
        globe.setOnRegionSelect((regionId) => {
          if (regionId !== originId) {
            destinationId = regionId;
            waypoints = waypoints.filter((w) => w !== destinationId);
            updateGlobeRoute();
            renderPanel(container);
            const reg = getRegionById(destinationId);
            if (reg.category === 'dangerous' && appState.settings.notifications?.riskAlerts) {
              showPriorityToastSwal({
                priority: reg.riskLevel === 'critico' ? 'P1' : 'P2',
                title: `Destino de Risco: ${reg.name}`,
                message: reg.conditions,
              });
            }
          }
        });
        updateGlobeRoute();
      }

      renderPanel(container);

      container.querySelector('#route-globe-reset')?.addEventListener('click', () => getSharedMarsGlobe()?.resetView());
    },

    unmount() {
      const globe = getSharedMarsGlobe();
      if (globe) {
        globe.setOnRegionSelect(null);
        globe.setRoute([]);
      }
    },
  };
}
