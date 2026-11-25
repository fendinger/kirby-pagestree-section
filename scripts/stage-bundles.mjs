import { existsSync, mkdirSync, renameSync, rmSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const variant = process.argv[2];

if (variant !== "k5" && variant !== "k6") {
  console.error("Usage: node scripts/stage-bundles.mjs k5|k6");
  process.exit(1);
}

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const target = join(root, "dist", variant);

mkdirSync(target, { recursive: true });

for (const file of ["index.js", "index.css"]) {
  const source = join(root, file);

  if (existsSync(source) === false) {
    continue;
  }

  const destination = join(target, file);
  rmSync(destination, { force: true });
  renameSync(source, destination);
}
