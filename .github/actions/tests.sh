#!/bin/bash

set -e

npx cypress run  --headless --browser chrome  --config '{"specPattern":["plugins/generic/coauthorAlert/cypress/tests/functional/*.cy.js"]}'
