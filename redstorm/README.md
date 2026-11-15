# REDSTORM

REDSTORM is non mandatory Saas integrated in the Marsis Ops system (Interplanetary Survival Guide: Martian Map) to be used as Mars storm simulator show-case of how Marsis Ops responds to this kind of dangerous events. 
The sistem is basically a backend system that uses json data-type to define the storm delaits, such as speed, aproximation time, visibility level, time until the end, affected areas and so on... Including math functions, logical approaches, and real-time rendering to create the closest view possible of a dangerous situation in Mars.

The system counts with an AI to give suppport about the procedures acording to storm's properties. And a an extra feedback for the frontend, to support the decision making process.

## TECNOLOGIES

### Core Technologies
-- Node.js
-- Express
-- Sqlite3

### Libraries
-- nodemon
-- socket.io

## Instalation
git clone https://github.com/Abilio007/Mar-s-Ops.git
cd redstorm
npm install
node server.js

## Test
*open a new cmd*
cd mar-s-ops
cd redstorm
node socketTest.js

