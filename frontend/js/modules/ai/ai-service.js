import { appState, MARS_REGIONS } from '../../core/state.js';
import { formatCoordinates, formatDistance, formatTemperature } from '../../utils/formatters.js';

/**
 * Independent AI integration layer.
 * External providers can be plugged in via configureExternalProvider(fn)
 * without coupling UI components to any specific SDK or endpoint.
 */
const aiConfig = {
  externalProvider: null,
  providerName: 'Base de Conhecimento Local (Sem API externa configurada)',
};

export function configureExternalAiProvider(providerFn, providerName = 'API Externa') {
  aiConfig.externalProvider = typeof providerFn === 'function' ? providerFn : null;
  aiConfig.providerName = aiConfig.externalProvider
    ? providerName
    : 'Base de Conhecimento Local (Sem API externa configurada)';
}

export function getAiIntegrationStatus() {
  return {
    hasExternalApi: Boolean(aiConfig.externalProvider),
    modeLabel: aiConfig.providerName,
  };
}

function normalizeText(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function generateLocalKnowledgeResponse(query) {
  const q = normalizeText(query);
  const distUnit = appState.settings.distanceUnit;
  const tempUnit = appState.settings.temperatureUnit;

  // Check if user asks about a specific region in MARS_REGIONS
  const matchedRegion = MARS_REGIONS.find((region) => {
    const nameNorm = normalizeText(region.name);
    const words = nameNorm.split(/\s+/).filter((w) => w.length > 3);
    return q.includes(nameNorm) || words.some((w) => q.includes(w));
  });

  if (matchedRegion) {
    const isSafe = matchedRegion.category === 'recommended';
    return {
      text: `${matchedRegion.name} encontra-se em ${formatCoordinates(matchedRegion.lat, matchedRegion.lon)}. ${matchedRegion.description} Condições atuais estimadas: ${matchedRegion.conditions}`,
      cards: [
        {
          title: matchedRegion.name,
          body: `${matchedRegion.terrain}. ${isSafe ? 'Zona apta para pouso e operações.' : 'Zona desaconselhada para pouso convencional.'}`,
          stats: [
            `Segurança: ${matchedRegion.safetyScore}`,
            `Temp: ${formatTemperature(matchedRegion.tempCelsius, tempUnit)}`,
          ],
        },
      ],
    };
  }

  // Safe vs Dangerous regions
  if (q.includes('segur') || q.includes('recomendad') || q.includes('pouso') || q.includes('perig') || q.includes('risco')) {
    const safeRegions = MARS_REGIONS.filter((r) => r.category === 'recommended').slice(0, 3);
    const dangerRegions = MARS_REGIONS.filter((r) => r.category === 'dangerous').slice(0, 3);
    return {
      text: 'Para operações seguras de superfície, priorize planícies de baixa elevação com boa exposição solar e declive reduzido. Evite grandes edifícios vulcânicos e cânions profundos onde a atmosfera rarefeita ou ventos catabáticos comprometem a descida.',
      cards: [
        {
          title: 'Zonas Recomendadas (Verdes)',
          body: safeRegions.map((r) => `${r.name} (${formatTemperature(r.tempCelsius, tempUnit)})`).join(' · '),
          stats: ['Baixo risco topográfico', 'Boa cobertura orbital'],
        },
        {
          title: 'Zonas Perigosas (Vermelhas)',
          body: dangerRegions.map((r) => `${r.name} (${r.riskLevel})`).join(' · '),
          stats: ['Restrição de pouso', 'Turbulência / Sismicidade'],
        },
      ],
    };
  }

  // Atmosphere & Temperature
  if (q.includes('atmosf') || q.includes('temperatur') || q.includes('clima') || q.includes('vento') || q.includes('poeira')) {
    return {
      text: `A atmosfera marciana é composta por cerca de 95,3% de dióxido de carbono (CO₂), 2,6% de nitrogénio e 1,9% de árgon, com pressão superficial média de apenas 6,1 mbar (~0,6% da Terra). As temperaturas variam entre ${formatTemperature(-125, tempUnit)} nos polos durante o inverno e ${formatTemperature(20, tempUnit)} ao meio-dia equatorial.`,
      cards: [
        {
          title: 'Perfil Atmosférico de Marte',
          body: 'Pressão média ~6,1 mbar · Gravidade 3,72 m/s² · Dia solar (Sol) de 24h 39m.',
          stats: ['95,3% CO₂', 'Temp. média: ' + formatTemperature(-63, tempUnit)],
        },
        {
          title: 'Risco de Poeira Sazonal',
          body: 'Durante o periélio austral, tempestades de poeira na Bacia de Hellas podem expandir-se e reduzir a geração fotovoltaica.',
          stats: ['Monitorização contínua', 'Requer reserva de energia'],
        },
      ],
    };
  }

  // Travel planning / routes / user trips
  if (q.includes('viag') || q.includes('rota') || q.includes('plane') || q.includes('miss') || q.includes('distancia')) {
    const activeTrips = appState.trips.filter((t) => t.status === 'active');
    const plannedTrips = appState.trips.filter((t) => t.status === 'planned');
    const sampleTrip = activeTrips[0] || plannedTrips[0];

    const tripNote = sampleTrip
      ? `Tem atualmente ${activeTrips.length} viagem(ns) em andamento e ${plannedTrips.length} planeada(s). A missão "${sampleTrip.name}" prevê percorrer ${formatDistance(sampleTrip.distanceKm, distUnit)} em ${sampleTrip.estimatedDays} Sóis.`
      : 'Ainda não possui viagens ativas; utilize o separador Planeamento de Viagens para traçar uma rota 3D com pontos de passagem.';

    return {
      text: `${tripNote} Ao planear uma rota marciana, evite cruzar diretamente Valles Marineris ou Tharsis Montes e inclua pontos de passagem intermédios em planícies estáveis como Elysium ou Cratera Gale.`,
      cards: [
        {
          title: 'Recomendações de Trajetória',
          body: 'Prefira corredores equatoriais entre Cratera Jezero, Cratera Gale e Elysium Planitia para minimizar desvios térmicos.',
          stats: ['Sol = 24,62 horas', 'Raio médio: ' + formatDistance(3389.5, distUnit)],
        },
      ],
    };
  }

  // Geography / general Mars info
  if (q.includes('geograf') || q.includes('olimpo') || q.includes('valles') || q.includes('marte') || q.includes('planeta')) {
    return {
      text: 'Marte apresenta uma dicotomia hemisférica marcante: planícies jovens e baixas no hemisfério norte e terras altas densamente crateradas no sul. Os maiores acidentes geográficos incluem o Monte Olimpo (21,9 km de altitude), o sistema de cânions Valles Marineris (4.000 km de extensão) e a Bacia de Hellas.',
      cards: [
        {
          title: 'Marcos Geográficos Principais',
          body: 'Monte Olimpo (maior vulcão) · Valles Marineris (maior cânion) · Bacia de Hellas (2.300 km de diâmetro).',
          stats: ['10 regiões cartografadas no globo', '5 zonas recomendadas'],
        },
      ],
    };
  }

  // Fallback contextual guidance
  return {
    text: 'Posso fornecer dados locais sobre geografia marciana, regiões seguras e perigosas, atmosfera e temperaturas, ou ajudar a rever o planeamento das suas viagens. Experimente perguntar sobre uma região específica como Cratera Jezero, Arcadia Planitia ou Valles Marineris.',
    cards: [
      {
        title: 'Tópicos Disponíveis (Modo Local)',
        body: 'Geografia de Marte · Zonas de Risco · Atmosfera e Temperaturas · Rotas e Missões Planeadas.',
        stats: ['Sem ligação externa ativa', 'Resposta instantânea'],
      },
    ],
  };
}

export async function queryMarsAssistant(userMessage) {
  if (aiConfig.externalProvider) {
    return await aiConfig.externalProvider(userMessage, {
      regions: MARS_REGIONS,
      trips: appState.trips,
      settings: appState.settings,
    });
  }

  // Short asynchronous yield to display processing indicator cleanly without faking an external API
  await new Promise((resolve) => window.setTimeout(resolve, 260));
  return generateLocalKnowledgeResponse(userMessage);
}
