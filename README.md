# MARTE — Rastreador de Exploração

Aplicação de demonstração para explorar Marte, planear trajetórias e acompanhar alertas. O frontend usa JavaScript modular, Three.js e armazenamento local; a API Laravel fornece autenticação, conversas com IA, alertas, heartbeats e planeamento de rotas com dados importados.

> **Limitação operacional:** dados visuais e telemetria simulada não são observações em tempo real. Coordenadas de heartbeat são reportadas pelo utilizador, não verificadas por GPS. Rotas geradas são apoio à decisão e não certificam segurança.

## Executar localmente

1. Configure o backend seguindo [backend/README.md](./backend/README.md). Inicie Laravel em `http://localhost:8000` e Reverb em `127.0.0.1:8080`.
2. No frontend, copie `frontend/.env.example` para `frontend/.env`. Configure `VITE_REVERB_APP_KEY` com a chave pública `REVERB_APP_KEY` gerada por `php artisan reverb:install`.
3. Execute `npm install` e `npm run dev` na pasta `frontend`.
4. Crie uma conta ou inicie sessão em **Definições → Conta e ligação**. Para usar o chat e o planeador remoto, configure também a chave do provedor de IA apenas no `.env` do backend.

O cliente usa token Sanctum em `sessionStorage`; o chat autenticado é guardado no backend e pode ser apagado pelo botão **Limpar**. Reverb entrega alertas e progresso por um canal privado autenticado.

## Estrutura

- `frontend/`: interface modular e cliente Laravel API/Reverb.
- `backend/`: Laravel API, persistência, alertas, planeamento por IA e broadcasting.