#!/usr/bin/env python3
"""Generate product category assignments from research CSV files."""

import argparse
import csv
import os
import re
import sys
from typing import Dict, List
from difflib import SequenceMatcher

# Common replacements for token normalization
REPLACEMENTS = {
    "lugs": "lug",
    "holes": "hole",
    "hh": "hole",
    "hub caps": "hubcap",
    "hub cap": "hubcap",
    "wheelcovers": "wheel cover",
    "wheelcover": "wheel cover",
    "wheel-simulator": "wheel simulator",
    "wheel-simulators": "wheel simulator",
    "over-lug": "over lug",
}

NEGATION_PATTERNS = [
    r"not\s+for\s+{}",
    r"does\s+not\s+fit\s+{}",
    r"without\s+{}",
    r"except\s+for\s+{}",
    r"not\s+compatible\s+with\s+{}",
    r"not\s+recommended\s+for\s+{}",
    r"not\s+intended\s+for\s+{}",
]


def normalize_text(text: str) -> str:
    text = text.lower()
    for key, val in REPLACEMENTS.items():
        text = re.sub(rf"\b{key}\b", val, text)
    # collapse extra whitespace
    return re.sub(r"\s+", " ", text)


def parse_cell(cell: str):
    cell = cell.strip()
    if '(' in cell and cell.endswith(')') and cell.rfind('(') < cell.rfind(')'):
        idx = cell.index('(')
        name = cell[:idx].strip()
        syns = [s.strip() for s in cell[idx + 1 : -1].split(',') if s.strip()]
    else:
        name = cell
        syns = []
    return name, syns


def load_category_mapping(path: str):
    mapping: Dict[str, List[str]] = {}
    patterns: Dict[str, re.Pattern] = {}

    def add_term(term: str, hierarchy: List[str]):
        norm = normalize_text(term)
        if norm not in mapping:
            mapping[norm] = hierarchy.copy()
            patterns[norm] = re.compile(r"(?<!\w)" + re.escape(norm) + r"(?!\w)")

    with open(path, newline='', encoding='utf-8') as f:
        reader = csv.reader(f)
        for row in reader:
            hierarchy: List[str] = []
            for cell in row:
                cell = cell.strip()
                if not cell:
                    continue
                name, syns = parse_cell(cell)
                hierarchy.append(name)
                for term in [name] + syns:
                    add_term(term, hierarchy)
                    if not term.endswith('s'):
                        add_term(term + 's', hierarchy)
                    if term.endswith('s'):
                        add_term(term[:-1], hierarchy)
                    if term == 'hole':
                        add_term('hh', hierarchy)
                        add_term('holes', hierarchy)
                    if term == 'lug':
                        add_term('lugs', hierarchy)
    return [(k, mapping[k], patterns[k]) for k in mapping]


def build_text(row: Dict[str, str]) -> str:
    parts = [
        row.get('Name', ''),
        row.get('Short description', ''),
        row.get('Description', ''),
        row.get('Brands', ''),
    ]
    for i in range(1, 23):
        parts.append(row.get(f'Attribute {i} name', ''))
        parts.append(row.get(f'Attribute {i} value(s)', ''))
    return normalize_text(' '.join(parts))


def assign_categories(text: str, mapping, fuzzy: bool = False, threshold: float = 0.85) -> List[str]:
    text = normalize_text(text)
    cats: List[str] = []
    words = text.split()
    for term, path, pattern in mapping:
        matched = False
        if pattern.search(text):
            matched = True
        elif fuzzy:
            term_words = term.split()
            n = len(term_words)
            for i in range(len(words) - n + 1):
                segment = " ".join(words[i:i + n])
                if SequenceMatcher(None, term, segment).ratio() >= threshold:
                    matched = True
                    break
        if matched:
            neg = False
            for pat in NEGATION_PATTERNS:
                if re.search(pat.format(re.escape(term)), text):
                    neg = True
                    break
            if neg:
                continue
            for c in path:
                if c not in cats:
                    cats.append(c)
    return cats


def main():
    parser = argparse.ArgumentParser(description='Generate product category assignments.')
    parser.add_argument('--products', default='Research/wc-products.csv', help='Path to products CSV')
    parser.add_argument('--categories', default='Research/Category Tree With Synonyms-Auto Enhance-13 JUN - Category Tree With Synonyms-Auto Enhance-13 JUN.csv', help='Path to category tree CSV')
    parser.add_argument('--output', default='product-categories.csv', help='Output CSV path')
    parser.add_argument('--fuzzy', action='store_true', help='Enable fuzzy matching for near terms')
    args = parser.parse_args()

    mapping = load_category_mapping(args.categories)

    # Allow extremely large fields for product descriptions
    csv.field_size_limit(sys.maxsize)

    with open(args.products, newline='', encoding='utf-8-sig') as pf, open(args.output, 'w', newline='', encoding='utf-8') as out:
        reader = csv.DictReader(pf)
        if reader.fieldnames and reader.fieldnames[0].startswith('\ufeff'):
            reader.fieldnames[0] = reader.fieldnames[0].lstrip('\ufeff')
        writer = csv.writer(out)
        for row in reader:
            sku = row.get('SKU', '').strip()
            if not sku:
                continue
            text = build_text(row)
            cats = assign_categories(text, mapping, args.fuzzy)
            if cats:
                writer.writerow([sku] + cats)


if __name__ == '__main__':
    main()
