import type {
    TemplateVariable,
    TemplateVariableValues,
} from '@/types/snippets';

const templateVariablePattern =
    /\{\{\{([A-Za-z_][A-Za-z0-9_]*):([\s\S]*?)\}\}\}/gu;

export function parseTemplateVariables(source: string): TemplateVariable[] {
    const variables = new Map<string, TemplateVariable>();

    for (const match of source.matchAll(templateVariablePattern)) {
        const [, name, defaultValue] = match;
        const existingVariable = variables.get(name);

        if (existingVariable) {
            existingVariable.occurrences += 1;

            continue;
        }

        variables.set(name, {
            name,
            defaultValue,
            occurrences: 1,
        });
    }

    return [...variables.values()];
}

export function getDefaultVariableValues(
    source: string,
): TemplateVariableValues {
    return Object.fromEntries(
        parseTemplateVariables(source).map(({ name, defaultValue }) => [
            name,
            defaultValue,
        ]),
    );
}

export function resolveTemplate(
    source: string,
    values: Readonly<Partial<TemplateVariableValues>> = {},
): string {
    const defaults = new Map<string, string>();

    return source.replace(
        templateVariablePattern,
        (_placeholder, name: string, declaredDefaultValue: string) => {
            if (!defaults.has(name)) {
                defaults.set(name, declaredDefaultValue);
            }

            if (
                Object.prototype.hasOwnProperty.call(values, name) &&
                values[name] !== undefined
            ) {
                return values[name];
            }

            return defaults.get(name) ?? declaredDefaultValue;
        },
    );
}

export const renderTemplate = resolveTemplate;
export const compileTemplate = resolveTemplate;
