"""Indent MODX HTML without rewriting attributes, inline prose or MODX expressions."""
import json
import re
from pathlib import Path
from html.parser import HTMLParser
from dataclasses import dataclass, field

ROOT = Path(__file__).resolve().parents[2]
VOID = set('area base br col embed hr img input link meta param source track wbr'.split())
PROSE = set('p h1 h2 h3 h4 h5 h6 dt dd span strong em b i small label time title textarea pre script style code option'.split())
INLINE = set('a button summary address figcaption legend'.split())
BLOCK = set('div section article aside nav header footer main ul ol li dl table thead tbody tfoot tr td th figure form fieldset details dialog'.split())

@dataclass
class Node:
    tag: str = ''
    opening: str = ''
    closing: str = ''
    children: list = field(default_factory=list)
    leaf: bool = False

class Markup(HTMLParser):
    def __init__(self, source):
        super().__init__(convert_charrefs=False)
        self.source = source
        self.root = Node()
        self.stack = [self.root]
        self.feed(source)
        self.close()
        if len(self.stack) != 1:
            raise ValueError('Unclosed markup: ' + repr([n.tag for n in self.stack[1:]]))

    def handle_starttag(self, tag, attrs):
        node = Node(tag=tag, opening=self.get_starttag_text(), leaf=tag in VOID)
        self.stack[-1].children.append(node)
        if not node.leaf:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        self.stack[-1].children.append(Node(tag=tag, opening=self.get_starttag_text(), leaf=True))

    def handle_endtag(self, tag):
        if len(self.stack) == 1 or self.stack[-1].tag != tag:
            raise ValueError('Mismatched closing tag: ' + tag)
        self.stack[-1].closing = '</' + tag + '>'
        self.stack.pop()

    def handle_data(self, data): self.stack[-1].children.append(data)
    def handle_entityref(self, name): self.handle_data('&' + name + ';')
    def handle_charref(self, name): self.handle_data('&#' + name + ';')
    def handle_comment(self, data): self.stack[-1].children.append(Node(opening='<!--'+data+'-->', leaf=True))
    def handle_decl(self, decl): self.stack[-1].children.append(Node(opening='<!'+decl+'>', leaf=True))
    def handle_pi(self, data): self.stack[-1].children.append(Node(opening='<?'+data+'>', leaf=True))

def raw(node):
    if isinstance(node, str): return node
    return node.opening + ''.join(raw(c) for c in node.children) + node.closing

def prose(text):
    return re.sub(r'\[\[[\s\S]*?\]\]', '', text).strip() != ''

def render(node, depth=0):
    indent = '\t' * depth
    if isinstance(node, str):
        value = node.strip()
        return indent + value if value else ''
    if node.leaf:
        return indent + node.opening
    if node.tag in PROSE or (node.tag in INLINE and not any(isinstance(c, Node) and c.tag in BLOCK for c in node.children)):
        return indent + raw(node)
    # Preserve mixed prose such as an icon followed directly by a word.
    if node.tag and any(isinstance(c, str) and prose(c) for c in node.children):
        return indent + raw(node)
    lines = [indent + node.opening] if node.opening else []
    child_depth = depth + (1 if node.opening else 0)
    for child in node.children:
        line = render(child, child_depth)
        if line: lines.append(line)
    if node.closing: lines.append(indent + node.closing)
    return '\n'.join(lines)

def format_html(source):
    shared_opening = re.match(r'^\s*(\[\[\$head\]\])', source)
    open_document = bool(re.match(r'^\s*<!doctype html>', source, re.I)) and '</html>' not in source.lower()
    closing_document = not shared_opening and '<html' not in source.lower() and bool(re.search(r'</body>\s*</html>\s*$', source, re.I))
    synthetic_closing = False
    if shared_opening:
        # A page template begins with the shared chunk, which opens html/body at runtime.
        source = '<html><body>' + source[shared_opening.end():]
        if '</html>' not in source.lower():
            source += '</body></html>'
            synthetic_closing = True
    elif open_document:
        # The head chunk deliberately ends after the opening body tag.
        source += '</body></html>'
    elif closing_document:
        source = '<html><body>' + source
    expressions = []
    def protect(match):
        expressions.append(match.group())
        return '[[MODX_FORMAT_TAG_' + str(len(expressions)-1) + ']]'
    protected = re.sub(r'\[\[[\s\S]*?\]\]', protect, source)
    formatted = render(Markup(protected).root).strip() + '\n'
    for index, expression in enumerate(expressions):
        formatted = formatted.replace('[[MODX_FORMAT_TAG_' + str(index) + ']]', expression)
    if shared_opening:
        lines = formatted.splitlines()
        formatted = '[[$head]]\n' + '\n'.join(lines[2:-2] if synthetic_closing else lines[2:]) + '\n'
    elif open_document:
        lines = formatted.splitlines()
        formatted = '\n'.join(lines[:-2]) + '\n'
    elif closing_document:
        formatted = '\n'.join(formatted.splitlines()[2:]) + '\n'
    return formatted

if __name__ == '__main__':
    source = json.loads((ROOT/'.tmp-formatting-input.json').read_text(encoding='utf-8-sig'))
    changes = []
    for entry in source:
        formatted = format_html(entry['before'])
        if format_html(formatted) != formatted:
            raise ValueError('Formatter is not idempotent: '+entry['kind']+' '+str(entry['id']))
        # Preserve every MODX tag verbatim, including expression whitespace and parameters.
        pattern = r'\[\[[\s\S]*?\]\]'
        if re.findall(pattern,entry['before']) != re.findall(pattern,formatted):
            raise ValueError('MODX tag changed')
        if formatted != entry['before']: changes.append(dict(entry, after=formatted))
    (ROOT/'.tmp-formatting-plan.json').write_text(json.dumps(changes,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({'changed':len(changes),'counts':{kind:sum(e['kind']==kind for e in changes) for kind in ['template','chunk','resource']}}))
