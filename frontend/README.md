# MARTE — Rastreador de Exploração

Aplicação web modular em **HTML5**, **CSS3**, **JavaScript ES6+**, **Three.js**, **Laravel API**, **Sanctum** e **Reverb**. O frontend continua sem framework de componentes. Regiões, catálogo, missões, alertas, posições e conversas vêm da API; apenas preferências visuais ficam no armazenamento local.

## Módulos da Aplicação

1. **Home (`js/modules/home/`)**: Globo tridimensional interativo de Marte com regiões do catálogo da API e alertas ativos recebidos do servidor.
2. **IA (`js/modules/ai/`)**: Chat autenticado com o agente Laravel; o histórico é carregado e guardado no servidor.
3. **Estado Atual (`js/modules/status/`)**: Missões, progresso, alertas e a última posição comunicada manualmente, sem telemetria simulada.
4. **Planeamento de Viagens (`js/modules/travel-planner/`)**: Criação, edição e remoção de missões persistidas na API; o catálogo e as métricas de rota vêm do servidor.
5. **Definições (`js/modules/settings/`)**: Preferências visuais, conta, ligação à API, heartbeat manual e indicação do estado Reverb.

## Ligação à API e Reverb

1. Copie `.env.example` para `.env`. Por padrão, o cliente espera a API em `http://localhost:8000/api`.
2. No `.env` do backend, permita a origem do Vite (`FRONTEND_ALLOWED_ORIGINS=http://localhost:3000`) e configure o provedor de IA apenas no servidor. Execute `php artisan migrate` e `php artisan db:seed` no backend para publicar o catálogo de regiões.
3. Execute `php artisan reverb:install` no backend e copie o valor público `REVERB_APP_KEY` para `VITE_REVERB_APP_KEY`. Defina `VITE_REVERB_HOST`, `VITE_REVERB_PORT` e `VITE_REVERB_SCHEME` de acordo com o endereço do Reverb acessível pelo browser. Se abrir o frontend por um IP da rede, use esse IP em `VITE_REVERB_HOST` e inclua apenas o host (sem esquema nem porta) em `REVERB_ALLOWED_ORIGINS` no backend. Nunca copie `REVERB_APP_SECRET` para o frontend.
4. Inicie o Laravel e o Reverb; execute `npm install` e `npm run dev` nesta pasta.
5. Inicie sessão em **Definições → Conta e ligação**. Alertas `warning`, `alarm` e `clear` chegam pelo canal privado `mars.user.{user_id}`. O mesmo canal atualiza o progresso da rota.

O utilizador pode enviar uma posição marciana manualmente na página de ligação. A aplicação não valida a origem da posição e nunca envia telemetria simulada como heartbeat. O catálogo de regiões é público; missões, alertas, heartbeats e chat requerem autenticação. Se a API falhar, os dados operacionais não são substituídos por dados locais.

## PWA

O build de produção gera o manifesto e o service worker. O shell e os assets versionados ficam disponíveis offline; pedidos `/api` são sempre enviados à rede e nunca entram no cache, incluindo conversas e dados autenticados. Para instalar, publique em HTTPS ou abra em `localhost` (service workers não funcionam em HTTP num IP da rede). Teste o build com `npm run build` e `npm run preview`.

## Validação

```sh
npm run lint
npm run build
```
