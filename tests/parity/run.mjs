// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { pathToFileURL } from "node:url";

const [upstream] = process.argv.slice(2);
const { propsBuilder } = await import(pathToFileURL(resolve(upstream, "index.js")).href);
const dir = import.meta.dirname;
const cases = JSON.parse(readFileSync(resolve(dir, "cases.json"), "utf8"));

console.warn = (message) => console.log(`WARNING: ${message}`);

/**
 * Reads the input of a parity case, Markdown files are passed as a string.
 *
 * @param {{input?: *, inputFile?: string}} parityCase
 * @returns {*}
 */
const readInput = ({ input, inputFile }) => {
	if (!inputFile) return input;

	const content = readFileSync(resolve(dir, inputFile), "utf8");
	return inputFile.endsWith(".md") ? content : JSON.parse(content);
};

for (const parityCase of cases) {
	console.log(`### ${parityCase.name}`);
	console.log(
		propsBuilder(readInput(parityCase), parityCase.filename, {
			...parityCase.options,
			writeToFile: false,
		}),
	);
}
