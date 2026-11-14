# MARTE — Rastreador de Exploração

Aplicação web modular em **HTML5**, **CSS3**, **JavaScript ES6+**, **Three.js**, **Laravel API**, **Sanctum** e **Reverb**. O frontend continua sem framework de componentes; o estado demonstrativo fica no `LocalStorage`, enquanto o token de autenticação e o identificador da conversa ficam apenas na sessão do browser.

## Módulos da Aplicação

1. **Home (`js/modules/home/`)**: Globo tridimensional interativo de Marte com rotação contínua, iluminação solar, atmosfera subtil, marcadores verdes (zonas recomendadas) e vermelhos (zonas perigosas), painel informativo do destino e painel de alertas.
2. **IA (`js/modules/ai/`)**: Assistente local demonstrativo sem sessão; com sessão iniciada, conversa com o agente Laravel e mantém o histórico no servidor. O botão **Limpar** apaga a conversa local e a conversa autenticada.
3. **Estado Atual (`js/modules/status/`)**: Painel de monitorização com indicadores circulares de progresso, cartões de telemetria simulada (explicitamente identificada como demonstração) e sistema de alertas de segurança.
4. **Planeamento de Viagens (`js/modules/travel-planner/`)**: Criação/edição de viagens com validação, seleção de destinos e rotas 3D com pontos de passagem, gestão por categorias (planeadas, em andamento, concluídas), vista detalhada e calendário de missões.
5. **Definições (`js/modules/settings/`)**: Configurações persistentes, conta, ligação à API, heartbeat manual e indicação do estado Reverb.

## Ligação à API e Reverb

1. Copie `.env.example` para `.env`. Por padrão, o cliente espera a API em `http://localhost:8000/api`.
2. No `.env` do backend, permita a origem do Vite (`FRONTEND_ALLOWED_ORIGINS=http://localhost:3000`) e configure o provedor de IA apenas no servidor.
3. Execute `php artisan reverb:install` no backend e copie o valor público `REVERB_APP_KEY` para `VITE_REVERB_APP_KEY`. Defina `VITE_REVERB_HOST`, `VITE_REVERB_PORT` e `VITE_REVERB_SCHEME` de acordo com o endereço público do Reverb. A origem permitida pelo servidor Reverb deve incluir o host do frontend.
4. Inicie o Laravel e o Reverb; execute `npm install` e `npm run dev` nesta pasta.
5. Inicie sessão em **Definições → Conta e ligação**. Alertas `warning`, `alarm` e `clear` chegam pelo canal privado `mars.user.{user_id}`. O mesmo canal atualiza o progresso da rota.

O utilizador pode enviar uma posição marciana manualmente na página de ligação. A aplicação não valida a origem da posição e nunca envia a telemetria simulada como heartbeat. Sem autenticação, o mapa, as viagens e as respostas locais permanecem em modo de demonstração.

## Validação

```sh
npm run lint
npm run build
```
