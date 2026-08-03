import type {
    TemplateVariable,
    TemplateVariableValues,
} from '@/types/snippets';

const templateVariablePattern =
    /\{\{\{([A-Za-z_][A-Za-z0-9_]*):([\s\S]*?)\}\}\}/gu;

export type TemplateHighlightRange = {
    start: number;
    end: number;
};

export type ResolvedTemplatePreview = {
    source: string;
    highlightRange: TemplateHighlightRange | null;
};

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

export function resolveTemplatePreview(
    source: string,
    highlightRange: TemplateHighlightRange | null,
    values: Readonly<Partial<TemplateVariableValues>> = {},
): ResolvedTemplatePreview {
    const resolvedSource = resolveTemplate(source, values);

    if (
        !highlightRange ||
        highlightRange.start < 0 ||
        highlightRange.end <= highlightRange.start ||
        highlightRange.end > source.length
    ) {
        return {
            source: resolvedSource,
            highlightRange: null,
        };
    }

    const highlightedSource = source.slice(
        highlightRange.start,
        highlightRange.end,
    );
    let markerIndex = 0;
    let startMarker = '';
    let endMarker = '';

    do {
        startMarker = `\u{E000}template-highlight-start-${markerIndex}\u{E001}`;
        endMarker = `\u{E000}template-highlight-end-${markerIndex}\u{E001}`;
        markerIndex += 1;
    } while (source.includes(startMarker) || source.includes(endMarker));

    const markedSource = `${source.slice(0, highlightRange.start)}${startMarker}${highlightedSource}${endMarker}${source.slice(highlightRange.end)}`;
    const resolvedMarkedSource = resolveTemplate(markedSource, values);
    const resolvedStart = resolvedMarkedSource.indexOf(startMarker);
    const resolvedEndMarker = resolvedMarkedSource.indexOf(
        endMarker,
        resolvedStart + startMarker.length,
    );

    if (resolvedStart < 0 || resolvedEndMarker < 0) {
        return {
            source: resolvedSource,
            highlightRange: null,
        };
    }

    const resolvedEnd = resolvedEndMarker - startMarker.length;

    if (
        resolvedSource.slice(resolvedStart, resolvedEnd) !== highlightedSource
    ) {
        return {
            source: resolvedSource,
            highlightRange: null,
        };
    }

    return {
        source: resolvedSource,
        highlightRange: {
            start: resolvedStart,
            end: resolvedEnd,
        },
    };
}

export const renderTemplate = resolveTemplate;
export const compileTemplate = resolveTemplate;
