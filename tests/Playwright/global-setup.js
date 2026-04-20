const { execSync } = require('node:child_process');

const defaultBaseUrl = 'http://127.0.0.1:8080';
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || defaultBaseUrl;
const assetResetCommand = process.env.PLAYWRIGHT_ASSET_RESET_COMMAND
  || "docker compose exec -T www sh -lc 'rm -rf public/assets'";
const migrateCommand = process.env.PLAYWRIGHT_MIGRATE_COMMAND
  || 'docker compose exec -T www php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration';
const seedCommand = process.env.PLAYWRIGHT_SEED_COMMAND || 'docker compose exec -T www php tests/Playwright/seed_docker_app.php';

async function waitForApp(url, timeoutMs = 60_000) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    try {
      const response = await fetch(url, { redirect: 'manual' });
      if (response.status < 500) {
        return;
      }
    } catch {
      // App not reachable yet.
    }

    await new Promise((resolve) => setTimeout(resolve, 1_000));
  }

  throw new Error(`Timed out waiting for ${url}`);
}

module.exports = async () => {
  execSync(assetResetCommand, { stdio: 'inherit' });
  execSync(migrateCommand, { stdio: 'inherit' });
  execSync(seedCommand, { stdio: 'inherit' });
  await waitForApp(`${baseUrl}/en/login`);
};
