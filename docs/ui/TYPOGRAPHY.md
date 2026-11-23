# Typography

## Font families
- English: Inter
- Hindi/Devanagari: Noto Sans Devanagari
- Fallback: system sans-serif stack

## Font loading
Prefer self-hosted font assets in production.
Do not make production rendering dependent on a third-party font CDN.
Before adding font packages, verify package licensing, available weights and generated asset size.

## Weights
- Regular: 400
- Medium: 500
- Semibold: 600
- Bold: 700

Use 400 for body text, 500 for labels, 600 for headings and important values.
Avoid excessive bold text.

## Rules
- Hindi and English must work in the same interface.
- Allow natural wrapping of Devanagari text.
- Do not force uppercase transformations on Hindi.
- Do not truncate customer names or financial descriptions without an accessible way to see the full value.
- Use tabular numerals for aligned financial values where supported.
