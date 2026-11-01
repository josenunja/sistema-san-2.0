"""Render the operator manual using bundled ReportLab; no application data read."""
from pathlib import Path
import html
import re
import json

from reportlab.pdfgen import canvas
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, PageBreak,
    Table, TableStyle, Preformatted, KeepTogether,
)
from reportlab.platypus.tableofcontents import TableOfContents
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'docs/MANUAL_COMPLETO_SAS_SAN.md'
TARGET = ROOT / 'output/pdf/Manual_completo_SAS_SAN.pdf'
TEMP = ROOT / 'tmp/pdfs'
TARGET.parent.mkdir(parents=True, exist_ok=True)
TEMP.mkdir(parents=True, exist_ok=True)

FONT_DIR = Path('C:/Windows/Fonts')
for alias, filename in [('Body', 'arial.ttf'), ('BodyBold', 'arialbd.ttf'), ('Code', 'consola.ttf')]:
    pdfmetrics.registerFont(TTFont(alias, str(FONT_DIR / filename)))
pdfmetrics.registerFontFamily('Body', normal='Body', bold='BodyBold', italic='Body', boldItalic='BodyBold')

INK = colors.HexColor('#15283D')
TEAL = colors.HexColor('#007C82')
GRAY = colors.HexColor('#526577')
LIGHT = colors.HexColor('#EFF5F7')
RULE = colors.HexColor('#D7E2E8')
PAGE_W, PAGE_H = 595.276, 841.89
LEFT, RIGHT, TOP, BOTTOM = 49, 49, 58, 48
WIDTH = PAGE_W - LEFT - RIGHT

styles = {
    'body': ParagraphStyle('body', fontName='Body', fontSize=10, leading=14.5, textColor=INK, spaceAfter=8),
    'h1': ParagraphStyle('h1', fontName='BodyBold', fontSize=21, leading=26, textColor=INK, spaceAfter=17, keepWithNext=True),
    'h2': ParagraphStyle('h2', fontName='BodyBold', fontSize=12, leading=17, textColor=TEAL, spaceBefore=11, spaceAfter=7, keepWithNext=True),
    'cell': ParagraphStyle('cell', fontName='Body', fontSize=9, leading=12.3, textColor=INK),
    'cellhead': ParagraphStyle('cellhead', fontName='BodyBold', fontSize=9, leading=12, textColor=colors.white),
    'list': ParagraphStyle('list', fontName='Body', fontSize=10, leading=14.2, textColor=INK, leftIndent=18, firstLineIndent=-15, spaceAfter=6),
    'code': ParagraphStyle('code', fontName='Code', fontSize=8.2, leading=12, textColor=INK, backColor=LIGHT, borderPadding=10, spaceBefore=5, spaceAfter=12),
    'small': ParagraphStyle('small', fontName='Body', fontSize=9, leading=13, textColor=GRAY, spaceAfter=9),
    'toc': ParagraphStyle('toc', fontName='Body', fontSize=10.2, leading=15, textColor=INK, spaceBefore=7, rightIndent=22),
}

def inline(text):
    text = html.escape(text)
    text = re.sub(r'`([^`]+)`', r'<font name="Code" size="9">\1</font>', text)
    text = re.sub(r'\*\*([^*]+)\*\*', r'<b>\1</b>', text)
    text = re.sub(r'(https?://[^\s<]+)', r'<link href="\1" color="#007C82">\1</link>', text)
    return text

class ManualDoc(BaseDocTemplate):
    def __init__(self, path):
        super().__init__(str(path), pagesize=(PAGE_W, PAGE_H), leftMargin=LEFT,
                         rightMargin=RIGHT, topMargin=TOP, bottomMargin=BOTTOM,
                         title='SAS SAN - Manual completo de instalación y operación',
                         author='SAS SAN', subject='Entrega local Windows, edición 31/10/2026')
        self.addPageTemplates(PageTemplate('normal', frames=[Frame(LEFT, BOTTOM, WIDTH, PAGE_H-TOP-BOTTOM, leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=self.decorate))
        self.chapter = ''

    def beforeDocument(self):
        self.chapter = ''

    def decorate(self, c, doc):
        c.saveState()
        if doc.page == 1:
            c.setFillColor(INK)
            c.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
            c.setFillColor(TEAL)
            c.rect(LEFT, 695, 64, 5, fill=1, stroke=0)
        else:
            c.setStrokeColor(RULE)
            c.line(LEFT, PAGE_H-36, PAGE_W-RIGHT, PAGE_H-36)
            c.setFont('BodyBold', 8)
            c.setFillColor(TEAL)
            c.drawString(LEFT, PAGE_H-26, 'SAS SAN  /  MANUAL COMPLETO')
            c.setFont('Body', 8)
            c.setFillColor(GRAY)
            c.drawRightString(PAGE_W-RIGHT, PAGE_H-26, 'ENTREGA LOCAL WINDOWS')
            c.line(LEFT, 33, PAGE_W-RIGHT, 33)
            c.drawString(LEFT, 21, 'Edición 31/10/2026  |  Una empresa por instalación')
            c.drawRightString(PAGE_W-RIGHT, 21, str(doc.page))
        c.restoreState()

    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph) and getattr(flowable, 'chapter_key', None):
            title = flowable.getPlainText()
            key = flowable.chapter_key
            self.canv.bookmarkPage(key)
            self.canv.addOutlineEntry(title, key, 0, False)
            self.notify('TOCEntry', (0, title, self.page, key))

story = []
cover_label = ParagraphStyle('coverlabel', fontName='BodyBold', fontSize=13, leading=20, textColor=colors.HexColor('#70D2CE'))
cover_title = ParagraphStyle('covertitle', fontName='BodyBold', fontSize=35, leading=42, textColor=colors.white, spaceAfter=23)
cover_sub = ParagraphStyle('coversub', fontName='Body', fontSize=15, leading=22, textColor=colors.HexColor('#DCE8EF'), spaceAfter=24)
cover_small = ParagraphStyle('coversmall', fontName='Body', fontSize=10, leading=16, textColor=colors.HexColor('#B8CEDB'))
story.extend([
    Spacer(1, 103), Paragraph('SAS SAN', cover_label), Spacer(1, 22),
    Paragraph('Tu negocio,<br/>paso a paso', cover_title),
    Paragraph('Manual completo de instalación,<br/>administración y operación', cover_sub),
    Spacer(1, 20),
    Paragraph('TIENDA  /  PRODUCTOS  /  INVENTARIO<br/>VENTAS  /  CAJA  /  EQUIPO  /  RESPALDOS', cover_small),
    Spacer(1, 69),
    Paragraph('EDICIÓN LOCAL WINDOWS<br/>31 de octubre de 2026<br/>Una empresa por instalación', cover_small),
    Spacer(1, 31),
    Paragraph('Para el propietario, su equipo y el técnico de entrega.<br/>Sin credenciales ni datos privados de clientes.', cover_small),
    PageBreak(), Paragraph('Índice de consulta', styles['h1']),
    Paragraph('Use los capítulos en orden para preparar el negocio. Durante la operación, consulte directamente el procedimiento que necesite. Los títulos y números del índice son enlaces dentro del PDF.', styles['body']),
])
toc = TableOfContents()
toc.levelStyles = [styles['toc']]
toc.dotsMinLevel = 0
story += [toc, PageBreak()]

lines = SOURCE.read_text(encoding='utf-8').splitlines()
start = next(i for i, line in enumerate(lines) if line.startswith('## 1.'))
i = start
chapters = []
while i < len(lines):
    line = lines[i].strip()
    if not line:
        i += 1
        continue
    if line.startswith('## '):
        if chapters:
            story.append(PageBreak())
        title = line[3:]
        p = Paragraph(inline(title), styles['h1'])
        p.chapter_key = 'cap-' + str(len(chapters)+1)
        story.append(p)
        chapters.append(title)
        i += 1
    elif line.startswith('### '):
        story.append(Paragraph(inline(line[4:]), styles['h2']))
        i += 1
    elif line.startswith('```'):
        code = []
        i += 1
        while i < len(lines) and not lines[i].startswith('```'):
            code.append(lines[i])
            i += 1
        # Wrap only long PowerShell commands at parameter boundaries, with the real continuation character.
        wrapped = []
        for code_line in code:
            if len(code_line) > 89 and code_line.startswith('.\\'):
                parts = re.split(r' (?=-[A-Za-z])', code_line)
                current = parts[0]
                for part in parts[1:]:
                    if len(current) + len(part) + 1 > 85:
                        wrapped.append(current + ' `')
                        current = '    ' + part
                    else:
                        current += ' ' + part
                wrapped.append(current)
            else:
                wrapped.append(code_line)
        story.append(Preformatted('\n'.join(wrapped), styles['code']))
        i += 1
    elif line.startswith('|'):
        rows = []
        while i < len(lines) and lines[i].strip().startswith('|'):
            cells = [c.strip() for c in lines[i].strip().strip('|').split('|')]
            if not all(re.fullmatch(r'[-: ]+', c) for c in cells):
                rows.append(cells)
            i += 1
        cols = len(rows[0])
        ratios = {2: [.36,.64], 5: [.40,.15,.15,.15,.15]}.get(cols, [1/cols]*cols)
        data = [[Paragraph(inline(cell), styles['cellhead' if idx == 0 else 'cell']) for cell in row] for idx,row in enumerate(rows)]
        table = Table(data, colWidths=[WIDTH*r for r in ratios], repeatRows=1, hAlign='LEFT')
        table.setStyle(TableStyle([
            ('BACKGROUND',(0,0),(-1,0),TEAL), ('VALIGN',(0,0),(-1,-1),'TOP'),
            ('LEFTPADDING',(0,0),(-1,-1),9), ('RIGHTPADDING',(0,0),(-1,-1),9),
            ('TOPPADDING',(0,0),(-1,-1),8), ('BOTTOMPADDING',(0,0),(-1,-1),8),
            ('ROWBACKGROUNDS',(0,1),(-1,-1),[LIGHT,colors.white]),
            ('LINEBELOW',(0,0),(-1,0),.4,TEAL),
            ('LINEBELOW',(0,1),(-1,-1),.3,RULE),
        ]))
        story += [table, Spacer(1, 12)]
    elif re.match(r'^(\d+\. |\- )', line):
        m = re.match(r'^(\d+\.|-) (.*)', line)
        prefix = m[1] if m[1] != '-' else '•'
        story.append(Paragraph(inline(prefix + '  ' + m[2]), styles['list']))
        i += 1
    else:
        para = [line]
        i += 1
        while i < len(lines) and lines[i].strip() and not re.match(r'^(#|\||```|\d+\. |\- )', lines[i]):
            para.append(lines[i].strip())
            i += 1
        story.append(Paragraph(inline(' '.join(para)), styles['body']))

doc = ManualDoc(TARGET)
doc.multiBuild(story)
reader = PdfReader(TARGET)
text = '\n'.join(p.extract_text() or '' for p in reader.pages)
assert len(chapters) == 28
assert all(ch in text for ch in chapters), 'Missing chapter in extracted text'
assert '\u25a0' not in text and '\ufffd' not in text, 'Unexpected replacement glyph'
assert len(reader.outline) == 28
report = {'pages': len(reader.pages), 'chapters': len(chapters), 'source_words': len(SOURCE.read_text(encoding='utf-8').split()), 'bytes': TARGET.stat().st_size}
(TEMP / 'manual-qa.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
(TEMP / 'manual-texto-extraido.txt').write_text(text, encoding='utf-8')
print(json.dumps(report))
