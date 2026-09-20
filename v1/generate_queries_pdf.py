"""
GymPro – SQL Queries Reference PDF Generator
Run: python generate_queries_pdf.py
Output: GymPro_SQL_Queries_Reference.pdf
"""

from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    HRFlowable, PageBreak, KeepTogether
)
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_JUSTIFY
from reportlab.platypus import Flowable
from datetime import date

# ── Colour palette ────────────────────────────────────────────
NAVY       = colors.HexColor('#0D1B2A')
NAVY_MID   = colors.HexColor('#162333')
NAVY_CARD  = colors.HexColor('#1C2E42')
BLUE       = colors.HexColor('#3A86FF')
GREEN      = colors.HexColor('#22C55E')
AMBER      = colors.HexColor('#F59E0B')
RED        = colors.HexColor('#EF4444')
TEXT       = colors.HexColor('#1a2940')
MUTED      = colors.HexColor('#4a6080')
BORDER     = colors.HexColor('#d0dce8')
LIGHT_BG   = colors.HexColor('#f0f5fa')
CODE_BG    = colors.HexColor('#0f1e2e')
CODE_TEXT  = colors.HexColor('#a8d8f0')
WHITE      = colors.white

# ── Styles ────────────────────────────────────────────────────
def make_styles():
    s = {}

    s['cover_title'] = ParagraphStyle(
        'cover_title', fontName='Helvetica-Bold',
        fontSize=32, textColor=WHITE, leading=40,
        alignment=TA_CENTER, spaceAfter=6
    )
    s['cover_sub'] = ParagraphStyle(
        'cover_sub', fontName='Helvetica',
        fontSize=13, textColor=colors.HexColor('#7ab8e0'),
        leading=18, alignment=TA_CENTER, spaceAfter=4
    )
    s['cover_meta'] = ParagraphStyle(
        'cover_meta', fontName='Helvetica',
        fontSize=10, textColor=colors.HexColor('#5a8aaa'),
        alignment=TA_CENTER
    )
    s['chapter'] = ParagraphStyle(
        'chapter', fontName='Helvetica-Bold',
        fontSize=18, textColor=NAVY, leading=24,
        spaceBefore=18, spaceAfter=4
    )
    s['section_num'] = ParagraphStyle(
        'section_num', fontName='Helvetica-Bold',
        fontSize=13, textColor=BLUE, leading=18,
        spaceBefore=14, spaceAfter=2
    )
    s['section_title'] = ParagraphStyle(
        'section_title', fontName='Helvetica-Bold',
        fontSize=12, textColor=NAVY, leading=16,
        spaceBefore=0, spaceAfter=3
    )
    s['body'] = ParagraphStyle(
        'body', fontName='Helvetica',
        fontSize=9.5, textColor=TEXT, leading=15,
        spaceBefore=2, spaceAfter=4, alignment=TA_JUSTIFY
    )
    s['bullet'] = ParagraphStyle(
        'bullet', fontName='Helvetica',
        fontSize=9.5, textColor=TEXT, leading=14,
        spaceBefore=1, spaceAfter=1,
        leftIndent=14, bulletIndent=4
    )
    s['code'] = ParagraphStyle(
        'code', fontName='Courier',
        fontSize=8.2, textColor=CODE_TEXT, leading=13,
        spaceBefore=0, spaceAfter=0,
        leftIndent=10, rightIndent=10
    )
    s['label'] = ParagraphStyle(
        'label', fontName='Helvetica-Bold',
        fontSize=8, textColor=MUTED, leading=11,
        spaceBefore=6, spaceAfter=2
    )
    s['toc_title'] = ParagraphStyle(
        'toc_title', fontName='Helvetica-Bold',
        fontSize=16, textColor=NAVY, leading=22,
        spaceBefore=0, spaceAfter=14, alignment=TA_CENTER
    )
    s['toc_entry'] = ParagraphStyle(
        'toc_entry', fontName='Helvetica',
        fontSize=10, textColor=TEXT, leading=16,
        spaceBefore=1, spaceAfter=1, leftIndent=8
    )
    s['toc_cat'] = ParagraphStyle(
        'toc_cat', fontName='Helvetica-Bold',
        fontSize=10.5, textColor=BLUE, leading=16,
        spaceBefore=8, spaceAfter=2
    )
    s['tag_ok'] = ParagraphStyle(
        'tag_ok', fontName='Helvetica-Bold',
        fontSize=7.5, textColor=WHITE, leading=10,
    )
    return s

# ── Custom Flowables ──────────────────────────────────────────
class ColorRect(Flowable):
    """Filled rectangle background."""
    def __init__(self, w, h, color):
        Flowable.__init__(self)
        self.w, self.h, self.color = w, h, color
    def draw(self):
        self.canv.setFillColor(self.color)
        self.canv.rect(0, 0, self.w, self.h, stroke=0, fill=1)

class CodeBlock(Flowable):
    """Dark-background SQL code block."""
    def __init__(self, sql_lines, width, style):
        Flowable.__init__(self)
        self.sql_lines = sql_lines
        self.avail_w   = width
        self.style     = style
        pad = 10
        line_h = 13
        self.block_h = len(sql_lines) * line_h + pad * 2
        self.pad = pad
        self.line_h = line_h

    def wrap(self, aw, ah):
        return self.avail_w, self.block_h

    def draw(self):
        c = self.canv
        w, h = self.avail_w, self.block_h
        # background
        c.setFillColor(CODE_BG)
        c.roundRect(0, 0, w, h, 5, stroke=0, fill=1)
        # left accent bar
        c.setFillColor(BLUE)
        c.rect(0, 0, 4, h, stroke=0, fill=1)
        # text
        c.setFont('Courier', 8.2)
        y = h - self.pad - 10
        for line in self.sql_lines:
            # colour keywords
            stripped = line
            kws = ['SELECT','FROM','WHERE','JOIN','LEFT','INNER','ON','GROUP BY',
                   'ORDER BY','INSERT','UPDATE','DELETE','SET','VALUES','AND',
                   'OR','NOT','NULL','COUNT','SUM','AS','BY','HAVING',
                   'COALESCE','CURDATE','INTERVAL','BETWEEN','LIKE','IN',
                   'DISTINCT','LIMIT','CREATE','TABLE','DATABASE','PRIMARY','KEY',
                   'FOREIGN','REFERENCES','AUTO_INCREMENT','ENGINE','TRUNCATE',
                   'INTO','CASE','WHEN','THEN','ELSE','END','DESC','ASC',
                   'DATE_ADD','DATE','INT','VARCHAR','DECIMAL']
            # Simple line colouring by content
            if any(line.strip().startswith(k) for k in ['--','#']):
                c.setFillColor(colors.HexColor('#6a9955'))
            else:
                # check if line is mostly keyword
                upper = line.upper()
                is_kw = any(upper.strip().startswith(k) for k in kws)
                c.setFillColor(CODE_TEXT if not is_kw else colors.HexColor('#569cd6'))
            c.drawString(12, y, line)
            y -= self.line_h

class TagBadge(Flowable):
    """Coloured pill badge."""
    def __init__(self, text, color=BLUE):
        Flowable.__init__(self)
        self.text  = text
        self.color = color
        self.pw    = len(text) * 5.5 + 14
        self.ph    = 14
    def wrap(self, aw, ah):
        return self.pw, self.ph
    def draw(self):
        c = self.canv
        c.setFillColor(self.color)
        c.roundRect(0, 0, self.pw, self.ph, 4, stroke=0, fill=1)
        c.setFillColor(WHITE)
        c.setFont('Helvetica-Bold', 7)
        c.drawCentredString(self.pw / 2, 3.5, self.text)

# ── Page templates ────────────────────────────────────────────
PAGE_W, PAGE_H = A4
MARGIN = 20 * mm

def on_first_page(canvas, doc):
    """Cover page: full navy background."""
    canvas.saveState()
    canvas.setFillColor(NAVY)
    canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
    # top accent strip
    canvas.setFillColor(BLUE)
    canvas.rect(0, PAGE_H - 6, PAGE_W, 6, stroke=0, fill=1)
    # bottom strip
    canvas.setFillColor(colors.HexColor('#1a3050'))
    canvas.rect(0, 0, PAGE_W, 28 * mm, stroke=0, fill=1)
    canvas.setFillColor(MUTED)
    canvas.setFont('Helvetica', 8)
    canvas.drawCentredString(PAGE_W / 2, 10 * mm,
        f'Generated {date.today().strftime("%d %B %Y")}  |  GymPro Management System  |  SQL Reference v1.0')
    canvas.restoreState()

def on_later_pages(canvas, doc):
    """Header + footer on content pages."""
    canvas.saveState()
    # header bar
    canvas.setFillColor(NAVY)
    canvas.rect(0, PAGE_H - 14 * mm, PAGE_W, 14 * mm, stroke=0, fill=1)
    canvas.setFillColor(BLUE)
    canvas.rect(0, PAGE_H - 14 * mm, 4, 14 * mm, stroke=0, fill=1)
    canvas.setFont('Helvetica-Bold', 8)
    canvas.setFillColor(WHITE)
    canvas.drawString(MARGIN, PAGE_H - 8.5 * mm, 'GymPro  |  SQL Queries Reference')
    canvas.setFont('Helvetica', 8)
    canvas.setFillColor(colors.HexColor('#7ab8e0'))
    canvas.drawRightString(PAGE_W - MARGIN, PAGE_H - 8.5 * mm, doc.title or '')
    # footer
    canvas.setFillColor(LIGHT_BG)
    canvas.rect(0, 0, PAGE_W, 11 * mm, stroke=0, fill=1)
    canvas.setFillColor(BORDER)
    canvas.rect(0, 11 * mm, PAGE_W, 0.4, stroke=0, fill=1)
    canvas.setFont('Helvetica', 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(MARGIN, 4 * mm, 'GymPro Membership Management System')
    canvas.drawRightString(PAGE_W - MARGIN, 4 * mm, f'Page {doc.page}')
    canvas.restoreState()

# ── Content builder ───────────────────────────────────────────
def divider(story, color=BORDER):
    story.append(Spacer(1, 4))
    story.append(HRFlowable(width='100%', thickness=0.8, color=color))
    story.append(Spacer(1, 6))

def chapter_header(story, styles, num, title, desc):
    story.append(PageBreak())
    # Chapter box
    data = [[Paragraph(f'{num}. {title}', ParagraphStyle(
        'ch_h', fontName='Helvetica-Bold', fontSize=16,
        textColor=WHITE, leading=22
    ))]]
    t = Table(data, colWidths=[PAGE_W - 2 * MARGIN])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), NAVY),
        ('TOPPADDING',    (0,0),(-1,-1), 12),
        ('BOTTOMPADDING', (0,0),(-1,-1), 12),
        ('LEFTPADDING',   (0,0),(-1,-1), 16),
        ('LINEABOVE',     (0,0),(-1,-1), 4, BLUE),
        ('ROUNDEDCORNERS',(0,0),(-1,-1), [6,6,6,6]),
    ]))
    story.append(t)
    story.append(Spacer(1, 8))
    story.append(Paragraph(desc, styles['body']))
    story.append(Spacer(1, 4))

def query_block(story, styles, num, title, purpose, sql_lines,
                returns='', notes='', tag_text='', tag_color=BLUE,
                used_in=''):
    avail = PAGE_W - 2 * MARGIN
    items = []

    # Number + title row
    header_data = [[
        Paragraph(f'Q{num}', ParagraphStyle(
            'qnum', fontName='Helvetica-Bold', fontSize=10,
            textColor=WHITE, alignment=TA_CENTER
        )),
        Paragraph(title, ParagraphStyle(
            'qtitle', fontName='Helvetica-Bold', fontSize=11,
            textColor=NAVY, leading=15
        ))
    ]]
    ht = Table(header_data, colWidths=[28, avail - 28])
    ht.setStyle(TableStyle([
        ('BACKGROUND',    (0,0),(0,0), BLUE),
        ('BACKGROUND',    (1,0),(1,0), LIGHT_BG),
        ('TOPPADDING',    (0,0),(-1,-1), 7),
        ('BOTTOMPADDING', (0,0),(-1,-1), 7),
        ('LEFTPADDING',   (0,0),(0,0), 0),
        ('LEFTPADDING',   (1,0),(1,0), 10),
        ('ALIGN',         (0,0),(0,0), 'CENTER'),
        ('VALIGN',        (0,0),(-1,-1), 'MIDDLE'),
        ('LINEBELOW',     (0,0),(-1,-1), 0.5, BORDER),
    ]))
    items.append(ht)

    # Purpose
    items.append(Spacer(1, 5))
    items.append(Paragraph('<b>Purpose:</b> ' + purpose, styles['body']))

    # Tag badge row
    if tag_text or used_in:
        row = []
        if tag_text:
            row.append(TagBadge(tag_text, tag_color))
        if used_in:
            row.append(Spacer(4, 1))
            row.append(Paragraph(
                f'<b>Used in:</b> {used_in}',
                ParagraphStyle('ui', fontName='Helvetica', fontSize=8.5,
                               textColor=MUTED, leading=12)
            ))
        # inline table
        col_w = [c.pw if hasattr(c,'pw') else avail - 80 for c in row]
        rt = Table([row], colWidths=col_w)
        rt.setStyle(TableStyle([
            ('VALIGN',(0,0),(-1,-1),'MIDDLE'),
            ('TOPPADDING',(0,0),(-1,-1),4),
            ('BOTTOMPADDING',(0,0),(-1,-1),0),
            ('LEFTPADDING',(0,0),(-1,-1),0),
        ]))
        items.append(rt)

    # SQL code
    items.append(Spacer(1, 6))
    items.append(Paragraph('SQL Query:', styles['label']))
    items.append(Spacer(1, 2))
    items.append(CodeBlock(sql_lines, avail, styles))

    # Returns
    if returns:
        items.append(Spacer(1, 5))
        items.append(Paragraph('<b>Returns:</b> ' + returns, styles['body']))

    # Notes
    if notes:
        items.append(Spacer(1, 3))
        note_data = [[Paragraph('Note: ' + notes, ParagraphStyle(
            'note', fontName='Helvetica-Oblique', fontSize=8.8,
            textColor=colors.HexColor('#5a6e80'), leading=13
        ))]]
        nt = Table(note_data, colWidths=[avail])
        nt.setStyle(TableStyle([
            ('BACKGROUND', (0,0),(-1,-1), colors.HexColor('#e8f0f8')),
            ('LEFTPADDING', (0,0),(-1,-1), 10),
            ('RIGHTPADDING', (0,0),(-1,-1), 10),
            ('TOPPADDING', (0,0),(-1,-1), 6),
            ('BOTTOMPADDING', (0,0),(-1,-1), 6),
            ('LINEBEFORE', (0,0),(0,-1), 3, AMBER),
        ]))
        items.append(nt)

    items.append(Spacer(1, 14))
    story.append(KeepTogether(items))

# ── MAIN ──────────────────────────────────────────────────────
def build():
    out = 'GymPro_SQL_Queries_Reference.pdf'
    doc = SimpleDocTemplate(
        out, pagesize=A4,
        leftMargin=MARGIN, rightMargin=MARGIN,
        topMargin=18*mm, bottomMargin=16*mm,
        title='GymPro SQL Reference',
        author='GymPro System'
    )
    styles = make_styles()
    story  = []

    # ── COVER ─────────────────────────────────────────────────
    story.append(Spacer(1, 55*mm))
    story.append(Paragraph('GymPro', styles['cover_title']))
    story.append(Spacer(1, 6))
    story.append(Paragraph('SQL Queries Reference', ParagraphStyle(
        'cs2', fontName='Helvetica-Bold', fontSize=20,
        textColor=colors.HexColor('#3A86FF'), leading=26, alignment=TA_CENTER
    )))
    story.append(Spacer(1, 10))
    story.append(Paragraph(
        'Complete documentation of all database queries used in the<br/>'
        'GymPro Membership Management System — with purpose,<br/>'
        'explanation and return values for every query.',
        styles['cover_sub']
    ))
    story.append(Spacer(1, 30*mm))

    # System info table
    info = [
        ['Database',  'gym_membership_db'],
        ['Tables',    '8 (MEMBERSHIP_PLAN, MEMBER, TRAINER, CLASS, ENROLLMENT, ATTENDANCE, PAYMENT, TRAINER_REQUEST)'],
        ['Queries',   '50 documented queries across 9 categories'],
        ['Engine',    'MySQL / MariaDB  •  InnoDB with Foreign Keys'],
        ['Generated', date.today().strftime('%d %B %Y')],
    ]
    avail = PAGE_W - 2*MARGIN
    t = Table(info, colWidths=[35*mm, avail - 35*mm])
    t.setStyle(TableStyle([
        ('BACKGROUND',    (0,0),(0,-1), colors.HexColor('#0a1520')),
        ('BACKGROUND',    (1,0),(1,-1), colors.HexColor('#111e2d')),
        ('FONTNAME',      (0,0),(0,-1), 'Helvetica-Bold'),
        ('FONTNAME',      (1,0),(1,-1), 'Helvetica'),
        ('FONTSIZE',      (0,0),(-1,-1), 8.5),
        ('TEXTCOLOR',     (0,0),(0,-1), colors.HexColor('#3A86FF')),
        ('TEXTCOLOR',     (1,0),(1,-1), colors.HexColor('#c8ddf0')),
        ('TOPPADDING',    (0,0),(-1,-1), 7),
        ('BOTTOMPADDING', (0,0),(-1,-1), 7),
        ('LEFTPADDING',   (0,0),(-1,-1), 12),
        ('GRID',          (0,0),(-1,-1), 0.3, colors.HexColor('#1a3050')),
    ]))
    story.append(t)

    # ── TABLE OF CONTENTS ──────────────────────────────────────
    story.append(PageBreak())
    story.append(Spacer(1, 8))
    story.append(Paragraph('Table of Contents', styles['toc_title']))
    divider(story, BLUE)

    toc = [
        ('1', 'Dashboard & Statistics Queries',       'Counts, revenue totals, expiring memberships, today\'s attendance'),
        ('2', 'Membership Plan Queries',               'List, create, update, delete membership plans; count members per plan'),
        ('3', 'Member Queries',                        'Register, search, filter, update, delete members; plan assignment'),
        ('4', 'Trainer Queries',                       'Add, list, update, delete trainers; salary and class summaries'),
        ('5', 'Class Queries',                         'Schedule, list, filter, update, delete classes; capacity checks'),
        ('6', 'Enrollment Queries',                    'Enroll members, check duplicates, remove enrollments, list by class'),
        ('7', 'Attendance Queries',                    'Mark, update, delete attendance; calculate attendance rate per class'),
        ('8', 'Payment Queries',                       'Record membership fees, trainer salaries, filter payments, totals'),
        ('9', 'Trainer Request Queries',               'Submit, accept, reject, delete trainer requests; status counts'),
    ]

    for num, title, desc in toc:
        story.append(Paragraph(
            f'<b>{num}.</b>  {title}',
            styles['toc_cat']
        ))
        story.append(Paragraph(desc, ParagraphStyle(
            'td', fontName='Helvetica-Oblique', fontSize=8.8,
            textColor=MUTED, leading=13, leftIndent=16, spaceAfter=4
        )))

    # ────────────────────────────────────────────────────────────
    # CHAPTER 1 — DASHBOARD
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 1, 'Dashboard & Statistics Queries',
        'These queries run every time the dashboard (index.php) loads. They provide '
        'live counts and summaries shown in the stat cards and quick-action panels.')

    query_block(story, styles, 1,
        'Total Members Count',
        'Counts every row in the MEMBER table to display the total number of registered members in the top stat card.',
        ['SELECT COUNT(*) AS n', 'FROM MEMBER'],
        returns='Single integer n — total number of members.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — "Total Members" stat card'
    )
    query_block(story, styles, 2,
        'Active Members Count',
        'Counts members whose plan end date is today or in the future, meaning their '
        'membership is still valid and they have access to the gym.',
        ['SELECT COUNT(*) AS n', 'FROM MEMBER', "WHERE PlanEndDate >= CURDATE()"],
        returns='Integer n — number of members with an active plan.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — subtitle of "Total Members" card'
    )
    query_block(story, styles, 3,
        'Total Revenue from Membership Fees',
        'Sums all payment amounts where the payment type is "Membership". '
        'COALESCE returns 0 instead of NULL when no payments exist yet.',
        ["SELECT COALESCE(SUM(Amount), 0) AS n",
         "FROM PAYMENT",
         "WHERE PaymentType = 'Membership'"],
        returns='Decimal n — total rupees collected from membership fees.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — "Revenue Collected" stat card'
    )
    query_block(story, styles, 4,
        'Total Scheduled Classes',
        'Counts all rows in the CLASS table, representing every class that has been scheduled in the system.',
        ['SELECT COUNT(*) AS n', 'FROM CLASS'],
        returns='Integer n — total classes in the system.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — "Classes Scheduled" stat card'
    )
    query_block(story, styles, 5,
        'Total Trainers Count',
        'Counts every trainer in the system, shown as a sub-label next to the classes stat.',
        ['SELECT COUNT(*) AS n', 'FROM TRAINER'],
        returns='Integer n — number of trainers on staff.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — subtitle of "Classes Scheduled" card'
    )
    query_block(story, styles, 6,
        "Today's Attendance Count",
        'Counts all attendance records for today where status is "Present". '
        "CURDATE() returns today's date dynamically without any hardcoded value.",
        ['SELECT COUNT(*) AS n',
         'FROM ATTENDANCE',
         "WHERE Date = CURDATE() AND Status = 'Present'"],
        returns="Integer n — number of members present today across all classes.",
        tag_text='READ', tag_color=BLUE,
        used_in="index.php — \"Today's Attendance\" stat card"
    )
    query_block(story, styles, 7,
        'Pending Trainer Requests Count',
        'Counts all trainer requests that have not yet been accepted or rejected, '
        'alerting staff to review them.',
        ['SELECT COUNT(*) AS n',
         'FROM TRAINER_REQUEST',
         "WHERE Status = 'Pending'"],
        returns='Integer n — number of unreviewed trainer requests.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — "Trainer Requests Pending" sub-label'
    )
    query_block(story, styles, 8,
        'Recent Member Registrations',
        'Retrieves the six most recently registered members joined with their plan '
        'name. Uses LEFT JOIN so members without a plan still appear.',
        ['SELECT m.Name, m.RegDate, p.PlanName',
         'FROM MEMBER m',
         'LEFT JOIN MEMBERSHIP_PLAN p ON m.PlanID = p.PlanID',
         'ORDER BY m.MemberID DESC',
         'LIMIT 6'],
        returns='Up to 6 rows: member name, registration date, plan name.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — "Recent Registrations" dashboard panel'
    )
    query_block(story, styles, 9,
        'Upcoming Classes List',
        'Fetches classes from today onward, ordered by nearest date first. '
        'LEFT JOIN includes the trainer name, showing "—" if no trainer is assigned.',
        ['SELECT c.ClassName, c.DateOfClass, c.StartTime, t.Name AS TrainerName',
         'FROM CLASS c',
         'LEFT JOIN TRAINER t ON c.TrainerID = t.TrainerID',
         'WHERE c.DateOfClass >= CURDATE()',
         'ORDER BY c.DateOfClass ASC, c.StartTime ASC',
         'LIMIT 6'],
        returns='Up to 6 upcoming classes with trainer names.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — "Upcoming Classes" dashboard panel'
    )
    query_block(story, styles, 10,
        'Memberships Expiring in Next 7 Days',
        'Finds members whose plan expires between today and 7 days from now. '
        'DATE_ADD() adds an interval to a date. Used to proactively alert staff to '
        'contact members for renewal.',
        ['SELECT Name, PlanEndDate',
         'FROM MEMBER',
         'WHERE PlanEndDate BETWEEN CURDATE()',
         '      AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
         'ORDER BY PlanEndDate ASC',
         'LIMIT 5'],
        returns='Up to 5 rows: member name and expiry date.',
        notes='BETWEEN is inclusive on both ends. Members expiring exactly today are included.',
        tag_text='READ', tag_color=BLUE,
        used_in='index.php — "Expiring in 7 Days" warning panel'
    )

    # ────────────────────────────────────────────────────────────
    # CHAPTER 2 — MEMBERSHIP PLANS
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 2, 'Membership Plan Queries',
        'Queries that manage the MEMBERSHIP_PLAN table. Plans define the duration '
        'and cost of gym membership packages available to members.')

    query_block(story, styles, 11,
        'List All Plans with Member Count',
        'Retrieves every membership plan along with a count of how many members are '
        'currently on each plan. Uses LEFT JOIN + GROUP BY so plans with zero members '
        'still appear in the list.',
        ['SELECT p.*, COUNT(m.MemberID) AS members',
         'FROM MEMBERSHIP_PLAN p',
         'LEFT JOIN MEMBER m ON m.PlanID = p.PlanID',
         'GROUP BY p.PlanID',
         'ORDER BY p.Price ASC'],
        returns='All plan columns + members count, sorted cheapest first.',
        tag_text='READ', tag_color=BLUE,
        used_in='plans.php — main plan list table'
    )
    query_block(story, styles, 12,
        'Create New Membership Plan',
        'Inserts a new plan row. Duration is stored in months (e.g. 3 = quarterly). '
        'Price is a DECIMAL(10,2) for accurate currency storage.',
        ['INSERT INTO MEMBERSHIP_PLAN (PlanName, PlanDetails, Duration, Price)',
         "VALUES ('Premium Annual', 'All access + PT sessions', 12, 22000.00)"],
        returns='Affected rows = 1 on success. New PlanID auto-generated.',
        tag_text='CREATE', tag_color=GREEN,
        used_in='plan_add.php — form submission'
    )
    query_block(story, styles, 13,
        'Update Existing Plan',
        'Updates all editable fields of a plan identified by PlanID. '
        'After a plan price changes, existing members are unaffected (their payment '
        'history already recorded the old price).',
        ['UPDATE MEMBERSHIP_PLAN',
         "SET PlanName='Elite Annual', PlanDetails='Unlimited access',",
         '    Duration=12, Price=25000.00',
         'WHERE PlanID = 4'],
        returns='Affected rows = 1 if updated, 0 if PlanID not found.',
        tag_text='UPDATE', tag_color=AMBER,
        used_in='plan_edit.php — form submission'
    )
    query_block(story, styles, 14,
        'Delete a Plan (Unlink Members First)',
        'Before deleting a plan, all members on that plan are unlinked by setting '
        'PlanID to NULL. Then the plan row is deleted. Two queries run in sequence.',
        ['-- Step 1: Unlink members',
         'UPDATE MEMBER',
         'SET PlanID=NULL, PlanStartDate=NULL, PlanEndDate=NULL',
         'WHERE PlanID = 4',
         '',
         '-- Step 2: Delete the plan',
         'DELETE FROM MEMBERSHIP_PLAN',
         'WHERE PlanID = 4'],
        returns='Step 1: rows updated. Step 2: 1 row deleted.',
        notes='The ON DELETE SET NULL foreign key constraint also handles this automatically, but explicit unlinking is done for clarity.',
        tag_text='DELETE', tag_color=RED,
        used_in='plan_delete.php — confirmation form'
    )
    query_block(story, styles, 15,
        'Get Plan Duration (for Auto-calculating End Date)',
        'Fetches only the Duration field of a selected plan, used to compute '
        'PlanEndDate = PlanStartDate + Duration months when assigning a plan to a member.',
        ['SELECT Duration',
         'FROM MEMBERSHIP_PLAN',
         'WHERE PlanID = 2'],
        returns='Single integer Duration (months).',
        tag_text='READ', tag_color=BLUE,
        used_in='member_add.php, member_edit.php — plan end date calculation'
    )

    # ────────────────────────────────────────────────────────────
    # CHAPTER 3 — MEMBERS
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 3, 'Member Queries',
        'Full CRUD plus search, filter, and profile-view queries for the MEMBER table. '
        'Member records are central to the system — nearly every other table links back here.')

    query_block(story, styles, 16,
        'List Members with Search & Filter',
        'The main member list query. Supports three simultaneous filters: '
        'text search across Name/NIC/Email, plan filter, and active/expired status filter. '
        'Filters are appended dynamically in PHP to the WHERE clause.',
        ['SELECT m.*, p.PlanName, p.Price',
         'FROM MEMBER m',
         'LEFT JOIN MEMBERSHIP_PLAN p ON m.PlanID = p.PlanID',
         "WHERE m.Name LIKE '%kasun%'",
         '  AND m.PlanID = 2',
         '  AND m.PlanEndDate >= CURDATE()',
         'ORDER BY m.MemberID DESC'],
        returns='Matching member rows with plan name and price.',
        notes='The actual WHERE clause is built dynamically — filters are added only if the user applied them.',
        tag_text='READ', tag_color=BLUE,
        used_in='members.php — member list with filters'
    )
    query_block(story, styles, 17,
        'Check NIC Uniqueness Before Insert',
        'Before saving a new member, this query checks whether the NIC already exists. '
        'NIC (National Identity Card) is a unique identifier in Sri Lanka.',
        ['SELECT MemberID',
         'FROM MEMBER',
         "WHERE NIC = '199512345678'"],
        returns='Row if NIC exists (duplicate), empty result if unique.',
        tag_text='VALIDATE', tag_color=AMBER,
        used_in='member_add.php — pre-insert validation'
    )
    query_block(story, styles, 18,
        'Insert New Member',
        'Registers a new member. Age is auto-calculated from DOB in PHP. '
        'PlanEndDate is computed as PlanStartDate + Duration months. '
        'NULL values are used for optional fields.',
        ['INSERT INTO MEMBER',
         '  (Name, NIC, DOB, Age, Gender, Email, Address,',
         '   PhoneNo, RegDate, PlanID, PlanStartDate, PlanEndDate)',
         'VALUES',
         "  ('Kasun Fernando', '199512345678', '1995-06-15',",
         "   29, 'Male', 'kasun@example.com', '12 Main St',",
         "   '0712345678', '2025-01-10', 4, '2025-01-10', '2026-01-10')"],
        returns='Affected rows = 1. New MemberID auto-incremented.',
        tag_text='CREATE', tag_color=GREEN,
        used_in='member_add.php — form submission'
    )
    query_block(story, styles, 19,
        'Update Member Record',
        'Updates all fields of a member by MemberID. If the plan changes, '
        'PlanEndDate is recalculated. If plan is removed, PlanID/dates are set to NULL.',
        ['UPDATE MEMBER SET',
         "  Name='Kasun Fernando', NIC='199512345678',",
         "  DOB='1995-06-15', Age=29, Gender='Male',",
         "  Email='kasun@example.com', Address='12 Main St',",
         "  PhoneNo='0712345678', RegDate='2025-01-10',",
         '  PlanID=4, PlanStartDate=\'2025-01-10\', PlanEndDate=\'2026-01-10\'',
         'WHERE MemberID = 1'],
        returns='Affected rows = 1 if updated.',
        tag_text='UPDATE', tag_color=AMBER,
        used_in='member_edit.php — form submission'
    )
    query_block(story, styles, 20,
        'Delete Member (Cascade All Related Records)',
        'Deletes a member and all their associated records in the correct dependency order '
        'to avoid foreign key constraint violations. The CASCADE setting handles this '
        'automatically, but explicit deletes are also performed.',
        ['DELETE FROM ENROLLMENT      WHERE MemberID = 1;',
         'DELETE FROM ATTENDANCE      WHERE MemberID = 1;',
         'DELETE FROM PAYMENT         WHERE MemberID = 1;',
         'DELETE FROM TRAINER_REQUEST WHERE MemberID = 1;',
         'DELETE FROM MEMBER          WHERE MemberID = 1;'],
        returns='Each query returns affected row count.',
        notes='ON DELETE CASCADE on foreign keys would handle child rows automatically, but explicit deletes are run for safety.',
        tag_text='DELETE', tag_color=RED,
        used_in='member_delete.php — confirmation form'
    )
    query_block(story, styles, 21,
        'Member Profile — Full Detail View',
        'Fetches a single member with their full plan details for the profile page.',
        ['SELECT m.*, p.PlanName, p.Duration, p.Price',
         'FROM MEMBER m',
         'LEFT JOIN MEMBERSHIP_PLAN p ON m.PlanID = p.PlanID',
         'WHERE m.MemberID = 1'],
        returns='Single row: all member fields + plan name, duration, price.',
        tag_text='READ', tag_color=BLUE,
        used_in='member_view.php — profile page header'
    )
    query_block(story, styles, 22,
        'Member Profile — Enrolled Classes',
        'Lists all classes a specific member is enrolled in, via the ENROLLMENT junction table.',
        ['SELECT c.ClassName, c.DateOfClass, c.StartTime',
         'FROM ENROLLMENT e',
         'JOIN CLASS c ON e.ClassID = c.ClassID',
         'WHERE e.MemberID = 1',
         'ORDER BY c.DateOfClass DESC'],
        returns='Class name, date, and start time for each enrolled class.',
        tag_text='READ', tag_color=BLUE,
        used_in='member_view.php — enrolled classes panel'
    )

    # ────────────────────────────────────────────────────────────
    # CHAPTER 4 — TRAINERS
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 4, 'Trainer Queries',
        'Queries for managing gym trainers. Trainers are linked to classes (via CLASS.TrainerID) '
        'and salary payments (via PAYMENT.TrainerID).')

    query_block(story, styles, 23,
        'List All Trainers with Class Count & Salary Paid',
        'Fetches every trainer with two aggregates: how many classes they teach '
        '(COUNT DISTINCT to avoid duplicates from the payment join) and total salary paid.',
        ['SELECT t.*,',
         '  COUNT(DISTINCT c.ClassID)  AS class_count,',
         "  COALESCE(SUM(p.Amount), 0) AS salary_paid",
         'FROM TRAINER t',
         'LEFT JOIN CLASS   c ON c.TrainerID = t.TrainerID',
         "LEFT JOIN PAYMENT p ON p.TrainerID = t.TrainerID AND p.PaymentType='Salary'",
         'GROUP BY t.TrainerID',
         'ORDER BY t.TrainerID DESC'],
        returns='All trainer columns + class_count + salary_paid.',
        notes='COUNT DISTINCT is essential here — without it, joining two LEFT JOINs would multiply rows and inflate the count.',
        tag_text='READ', tag_color=BLUE,
        used_in='trainers.php — trainer list table'
    )
    query_block(story, styles, 24,
        'Insert New Trainer',
        'Adds a new trainer. Experience is stored in years as an integer.',
        ['INSERT INTO TRAINER (Name, PhoneNo, Email, Experience)',
         "VALUES ('Ashan Perera', '0771234567', 'ashan@gym.lk', 7)"],
        returns='Affected rows = 1. New TrainerID auto-generated.',
        tag_text='CREATE', tag_color=GREEN,
        used_in='trainer_add.php — form submission'
    )
    query_block(story, styles, 25,
        'Auto-calculate Trainer Salary from Classes',
        'Before showing the payment form, this query sums up RatePerClass for each '
        'trainer across all their classes. This auto-fills the Amount field when '
        'recording a salary payment.',
        ['SELECT t.TrainerID, t.Name,',
         '  COALESCE(SUM(c.RatePerClass), 0) AS calc_salary',
         'FROM TRAINER t',
         'LEFT JOIN CLASS c ON c.TrainerID = t.TrainerID',
         'WHERE c.RatePerClass IS NOT NULL',
         'GROUP BY t.TrainerID'],
        returns='TrainerID, Name, calculated total salary from class rates.',
        tag_text='READ', tag_color=BLUE,
        used_in='payment_add.php — auto-fill salary amount'
    )
    query_block(story, styles, 26,
        'Delete Trainer (Unlink Classes & Payments First)',
        'Removes a trainer safely by first clearing their TrainerID from related records, '
        'then deleting their requests and finally the trainer row itself.',
        ['DELETE FROM TRAINER_REQUEST WHERE TrainerID = 1;',
         'UPDATE CLASS   SET TrainerID = NULL WHERE TrainerID = 1;',
         'UPDATE PAYMENT SET TrainerID = NULL WHERE TrainerID = 1;',
         'DELETE FROM TRAINER WHERE TrainerID = 1;'],
        returns='Each query returns affected row count.',
        tag_text='DELETE', tag_color=RED,
        used_in='trainer_delete.php — confirmation form'
    )

    # ────────────────────────────────────────────────────────────
    # CHAPTER 5 — CLASSES
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 5, 'Class Queries',
        'Classes are scheduled gym sessions tied to a trainer and a date/time. '
        'Capacity is enforced at the enrollment stage.')

    query_block(story, styles, 27,
        'List All Classes with Trainer & Enrollment Count',
        'The main class list. Counts enrolled members per class and compares to '
        'Capacity. Uses GROUP BY with COUNT DISTINCT to avoid join inflation.',
        ['SELECT c.*, t.Name AS TrainerName,',
         '  COUNT(DISTINCT e.EnrollmentID) AS enrolled',
         'FROM CLASS c',
         'LEFT JOIN TRAINER    t ON c.TrainerID = t.TrainerID',
         'LEFT JOIN ENROLLMENT e ON e.ClassID   = c.ClassID',
         'GROUP BY c.ClassID',
         'ORDER BY c.DateOfClass DESC, c.StartTime ASC'],
        returns='All class columns + trainer name + enrollment count.',
        tag_text='READ', tag_color=BLUE,
        used_in='classes.php — class list table'
    )
    query_block(story, styles, 28,
        'List Classes with Available Spots (for Enrollment)',
        'When enrolling a member, only classes that still have capacity are shown. '
        'HAVING filters groups after aggregation — it cannot be used in WHERE.',
        ['SELECT c.ClassID, c.ClassName, c.DateOfClass, c.StartTime,',
         '  c.Capacity, COUNT(e.EnrollmentID) AS enrolled',
         'FROM CLASS c',
         'LEFT JOIN ENROLLMENT e ON e.ClassID = c.ClassID',
         'GROUP BY c.ClassID',
         'HAVING enrolled < c.Capacity',
         'ORDER BY c.DateOfClass DESC'],
        returns='Only classes where enrolled count is less than capacity.',
        notes='HAVING is used (not WHERE) because the filter is on an aggregate value (COUNT).',
        tag_text='READ', tag_color=BLUE,
        used_in='enrollment_add.php — class dropdown'
    )
    query_block(story, styles, 29,
        'Insert New Class',
        'Schedules a new class. TrainerID and RatePerClass are nullable — '
        'a class can exist without a trainer initially.',
        ['INSERT INTO CLASS',
         '  (ClassName, DateOfClass, StartTime, EndTime,',
         '   Description, Capacity, TrainerID, Specialization, RatePerClass)',
         'VALUES',
         "  ('Morning Yoga', '2025-09-10', '06:00', '07:00',",
         "   'Beginner session', 15, 1, 'Yoga', 1500.00)"],
        returns='Affected rows = 1. ClassID auto-generated.',
        tag_text='CREATE', tag_color=GREEN,
        used_in='class_add.php — form submission'
    )
    query_block(story, styles, 30,
        'Delete Class (Cascade Enrollments & Attendance)',
        'Removes a class and all linked enrollment and attendance records to maintain '
        'referential integrity.',
        ['DELETE FROM ENROLLMENT WHERE ClassID = 1;',
         'DELETE FROM ATTENDANCE WHERE ClassID = 1;',
         'DELETE FROM CLASS      WHERE ClassID = 1;'],
        returns='Each statement returns affected row count.',
        tag_text='DELETE', tag_color=RED,
        used_in='class_delete.php — confirmation form'
    )

    # ────────────────────────────────────────────────────────────
    # CHAPTER 6 — ENROLLMENT
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 6, 'Enrollment Queries',
        'ENROLLMENT is the junction table linking MEMBER and CLASS in a many-to-many '
        'relationship. A member can be in many classes, and a class has many members.')

    query_block(story, styles, 31,
        'List All Enrollments (with Search)',
        'Joins ENROLLMENT with MEMBER and CLASS to show meaningful names '
        'instead of raw IDs. Supports an optional name/class search.',
        ['SELECT e.EnrollmentID, m.Name AS MemberName, m.MemberID,',
         '  c.ClassName, c.DateOfClass, c.StartTime, c.ClassID',
         'FROM ENROLLMENT e',
         'JOIN MEMBER m ON e.MemberID = m.MemberID',
         'JOIN CLASS  c ON e.ClassID  = c.ClassID',
         "WHERE m.Name LIKE '%kasun%'",
         'ORDER BY c.DateOfClass DESC, m.Name ASC'],
        returns='EnrollmentID, member name, class name, date, time.',
        tag_text='READ', tag_color=BLUE,
        used_in='enrollments.php — enrollment list'
    )
    query_block(story, styles, 32,
        'Check for Duplicate Enrollment',
        'Before inserting a new enrollment, this verifies the member is not already '
        'enrolled in the same class. Prevents duplicate ENROLLMENT rows.',
        ['SELECT EnrollmentID',
         'FROM ENROLLMENT',
         'WHERE MemberID = 1 AND ClassID = 2'],
        returns='Row if duplicate exists, empty result if enrollment is valid.',
        tag_text='VALIDATE', tag_color=AMBER,
        used_in='enrollment_add.php — pre-insert check'
    )
    query_block(story, styles, 33,
        'Insert New Enrollment',
        'Adds a member to a class. The combination of MemberID + ClassID uniquely '
        'identifies the enrollment.',
        ['INSERT INTO ENROLLMENT (MemberID, ClassID)',
         'VALUES (1, 2)'],
        returns='Affected rows = 1. EnrollmentID auto-generated.',
        tag_text='CREATE', tag_color=GREEN,
        used_in='enrollment_add.php — form submission'
    )
    query_block(story, styles, 34,
        'Delete (Remove) an Enrollment',
        'Unenrolls a member from a class by deleting the specific ENROLLMENT row.',
        ['DELETE FROM ENROLLMENT',
         'WHERE EnrollmentID = 5'],
        returns='Affected rows = 1.',
        tag_text='DELETE', tag_color=RED,
        used_in='enrollment_delete.php — confirmation form'
    )

    # ────────────────────────────────────────────────────────────
    # CHAPTER 7 — ATTENDANCE
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 7, 'Attendance Queries',
        'Attendance records link a member, a class, and a date with a Present/Absent status. '
        'The mark-attendance page uses an upsert pattern to update if a record exists or insert if not.')

    query_block(story, styles, 35,
        'List Attendance with Filters',
        'Fetches attendance records with optional filters for date, class, and status. '
        'Two separate WHERE strings are built — one with table alias for the JOIN query, '
        'one without alias for plain COUNT queries.',
        ['SELECT a.*, m.Name AS MemberName, c.ClassName',
         'FROM ATTENDANCE a',
         'JOIN MEMBER m ON a.MemberID = m.MemberID',
         'JOIN CLASS  c ON a.ClassID  = c.ClassID',
         "WHERE a.Date = '2025-09-08'",
         '  AND a.ClassID = 1',
         "  AND a.Status = 'Present'",
         'ORDER BY a.Date DESC, m.Name ASC'],
        returns='Matching attendance rows with member and class names.',
        tag_text='READ', tag_color=BLUE,
        used_in='attendance.php — attendance list with filters'
    )
    query_block(story, styles, 36,
        'Count Total & Present Attendance (for Rate)',
        'Two plain COUNT queries used to calculate the attendance rate percentage '
        'shown above the table. Uses column names without alias (no JOIN needed).',
        ['-- Total records matching the filter',
         'SELECT COUNT(*) AS n',
         'FROM ATTENDANCE',
         "WHERE Date = '2025-09-08' AND ClassID = 1",
         '',
         '-- Present count (adds Status filter)',
         'SELECT COUNT(*) AS n',
         'FROM ATTENDANCE',
         "WHERE Date = '2025-09-08' AND ClassID = 1 AND Status = 'Present'"],
        returns='Two integers: total records, and present count.',
        notes='Rate = present / total * 100. Division by zero is guarded by checking total > 0 in PHP.',
        tag_text='READ', tag_color=BLUE,
        used_in='attendance.php — attendance rate display'
    )
    query_block(story, styles, 37,
        'Load Members for Attendance Marking',
        'When marking attendance for a class, retrieves all enrolled members plus '
        'their existing attendance status for that class on the given date (if any). '
        'A subquery checks the ATTENDANCE table inline.',
        ['SELECT m.MemberID, m.Name,',
         '  (SELECT Status FROM ATTENDANCE',
         '   WHERE MemberID = m.MemberID',
         '     AND ClassID = 1',
         "     AND Date = '2025-09-08') AS status",
         'FROM ENROLLMENT e',
         'JOIN MEMBER m ON e.MemberID = m.MemberID',
         'WHERE e.ClassID = 1',
         'ORDER BY m.Name'],
        returns='Member ID, name, and existing attendance status (NULL if not yet marked).',
        notes='The correlated subquery returns NULL when no attendance record exists — PHP treats this as "not yet marked" and defaults the radio to Present.',
        tag_text='READ', tag_color=BLUE,
        used_in='attendance_mark.php — member list with radio buttons'
    )
    query_block(story, styles, 38,
        'Upsert Attendance (Insert or Update)',
        'For each member in the class, checks if an attendance record already exists. '
        'If it does, UPDATE is used. If not, INSERT is used. This is the upsert pattern.',
        ['-- Check if record exists',
         'SELECT AttendanceID FROM ATTENDANCE',
         "WHERE MemberID=1 AND ClassID=1 AND Date='2025-09-08'",
         '',
         '-- If exists: update',
         "UPDATE ATTENDANCE SET Status='Present'",
         'WHERE AttendanceID = 7',
         '',
         '-- If not: insert',
         'INSERT INTO ATTENDANCE (Status, Date, MemberID, ClassID)',
         "VALUES ('Present', '2025-09-08', 1, 1)"],
        returns='1 affected row per member per call.',
        notes='This allows re-marking attendance for the same session without creating duplicate records.',
        tag_text='UPSERT', tag_color=AMBER,
        used_in='attendance_mark.php — form submission loop'
    )

    # ────────────────────────────────────────────────────────────
    # CHAPTER 8 — PAYMENTS
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 8, 'Payment Queries',
        'The PAYMENT table serves dual purpose: recording membership fees paid by members '
        'and salary payments made to trainers. PaymentType distinguishes the two.')

    query_block(story, styles, 39,
        'List Payments with Filters',
        'Retrieves payments with optional filters for type (Membership/Salary), '
        'payment method, and date range. Uses aliased WHERE for the JOIN query.',
        ['SELECT p.*, m.Name AS MemberName, t.Name AS TrainerName',
         'FROM PAYMENT p',
         'LEFT JOIN MEMBER  m ON p.MemberID  = m.MemberID',
         'LEFT JOIN TRAINER t ON p.TrainerID = t.TrainerID',
         "WHERE p.PaymentType = 'Membership'",
         "  AND p.PaymentMethod = 'Cash'",
         "  AND p.PaymentDate >= '2025-01-01'",
         "  AND p.PaymentDate <= '2025-12-31'",
         'ORDER BY p.PaymentDate DESC, p.PaymentID DESC'],
        returns='Payment rows with member/trainer names where applicable.',
        tag_text='READ', tag_color=BLUE,
        used_in='payments.php — payment list with filters'
    )
    query_block(story, styles, 40,
        'Payment Totals (Membership, Salary, Grand Total)',
        'A single query using CASE WHEN to split the SUM into membership vs salary, '
        'plus a grand total. Uses plain WHERE (no alias) since it is a single-table query.',
        ['SELECT',
         "  SUM(CASE WHEN PaymentType='Membership' THEN Amount ELSE 0 END) AS membership,",
         "  SUM(CASE WHEN PaymentType='Salary'     THEN Amount ELSE 0 END) AS salary,",
         '  SUM(Amount) AS total',
         'FROM PAYMENT',
         "WHERE PaymentType = 'Membership'",
         "  AND PaymentDate >= '2025-01-01'"],
        returns='Three decimal values: membership total, salary total, grand total.',
        notes='CASE WHEN inside SUM is a conditional aggregate — it sums only the rows matching that condition.',
        tag_text='READ', tag_color=BLUE,
        used_in='payments.php — three summary stat cards'
    )
    query_block(story, styles, 41,
        'Insert Membership Payment',
        'Records a fee paid by a member. TrainerID is NULL for membership payments.',
        ['INSERT INTO PAYMENT',
         '  (PaymentDate, PaymentMethod, Amount, PaymentType, MemberID, TrainerID)',
         "VALUES ('2025-09-08', 'Cash', 22000.00, 'Membership', 1, NULL)"],
        returns='Affected rows = 1. PaymentID auto-generated.',
        tag_text='CREATE', tag_color=GREEN,
        used_in='payment_add.php — membership fee form'
    )
    query_block(story, styles, 42,
        'Insert Trainer Salary Payment',
        'Records a salary payment to a trainer. MemberID is NULL for salary payments.',
        ['INSERT INTO PAYMENT',
         '  (PaymentDate, PaymentMethod, Amount, PaymentType, MemberID, TrainerID)',
         "VALUES ('2025-08-31', 'Bank Transfer', 45000.00, 'Salary', NULL, 1)"],
        returns='Affected rows = 1.',
        notes='The PaymentType column distinguishes membership fees from salary entries, allowing both to share the same table.',
        tag_text='CREATE', tag_color=GREEN,
        used_in='payment_add.php — salary form'
    )

    # ────────────────────────────────────────────────────────────
    # CHAPTER 9 — TRAINER REQUESTS
    # ────────────────────────────────────────────────────────────
    chapter_header(story, styles, 9, 'Trainer Request Queries',
        'Members can request a specific trainer. Staff can accept or reject requests. '
        'Status values are: Pending, Accepted, Rejected.')

    query_block(story, styles, 43,
        'List All Requests with Status Filter',
        'Fetches all trainer requests with member and trainer names. '
        'Optional status filter allows viewing only Pending, Accepted, or Rejected.',
        ['SELECT tr.*, m.Name AS MemberName, t.Name AS TrainerName',
         'FROM TRAINER_REQUEST tr',
         'JOIN MEMBER  m ON tr.MemberID  = m.MemberID',
         'JOIN TRAINER t ON tr.TrainerID = t.TrainerID',
         "WHERE tr.Status = 'Pending'",
         'ORDER BY tr.RequestDate DESC, tr.RequestID DESC'],
        returns='Request rows with member and trainer names.',
        tag_text='READ', tag_color=BLUE,
        used_in='trainer_requests.php — main request table'
    )
    query_block(story, styles, 44,
        'Count Requests by Status',
        'Counts requests grouped by their Status column. Returns one row per status '
        'value. Used to display the three mini-stat badges at the top of the page.',
        ['SELECT Status, COUNT(*) AS n',
         'FROM TRAINER_REQUEST',
         'GROUP BY Status'],
        returns="Up to 3 rows: one each for 'Pending', 'Accepted', 'Rejected'.",
        tag_text='READ', tag_color=BLUE,
        used_in='trainer_requests.php — Pending / Accepted / Rejected badges'
    )
    query_block(story, styles, 45,
        'Insert New Trainer Request',
        'Submits a new request from a member for a specific trainer. '
        'Status defaults to "Pending".',
        ['INSERT INTO TRAINER_REQUEST (RequestDate, Status, MemberID, TrainerID)',
         "VALUES ('2025-09-08', 'Pending', 3, 1)"],
        returns='Affected rows = 1. RequestID auto-generated.',
        tag_text='CREATE', tag_color=GREEN,
        used_in='request_add.php — form submission'
    )
    query_block(story, styles, 46,
        'Accept or Reject a Request',
        'A single UPDATE changes the Status of a request. The $status variable '
        'in PHP is validated to only allow "Accepted" or "Rejected" before the query runs.',
        ['UPDATE TRAINER_REQUEST',
         "SET Status = 'Accepted'",
         'WHERE RequestID = 3'],
        returns='Affected rows = 1 if updated.',
        notes="PHP validates $status ∈ {'Accepted','Rejected'} before running this query to prevent arbitrary status values.",
        tag_text='UPDATE', tag_color=AMBER,
        used_in='request_update.php — one-click Accept / Reject buttons'
    )
    query_block(story, styles, 47,
        'Delete a Trainer Request',
        'Permanently removes a request record regardless of its status.',
        ['DELETE FROM TRAINER_REQUEST',
         'WHERE RequestID = 3'],
        returns='Affected rows = 1.',
        tag_text='DELETE', tag_color=RED,
        used_in='request_delete.php — confirmation form'
    )

    # ────────────────────────────────────────────────────────────
    # BONUS — SETUP & ADMIN QUERIES
    # ────────────────────────────────────────────────────────────
    story.append(PageBreak())
    avail = PAGE_W - 2*MARGIN
    data = [[Paragraph('Bonus: Setup & Admin Queries', ParagraphStyle(
        'bh', fontName='Helvetica-Bold', fontSize=16, textColor=WHITE, leading=22
    ))]]
    t = Table(data, colWidths=[avail])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0,0),(-1,-1), colors.HexColor('#1a3050')),
        ('TOPPADDING',    (0,0),(-1,-1), 12),
        ('BOTTOMPADDING', (0,0),(-1,-1), 12),
        ('LEFTPADDING',   (0,0),(-1,-1), 16),
        ('LINEABOVE',     (0,0),(-1,-1), 4, GREEN),
    ]))
    story.append(t)
    story.append(Spacer(1, 8))
    story.append(Paragraph(
        'These queries are used by setup.php and seed.php for initial database creation '
        'and data seeding. They run once during system setup, not in normal operation.',
        styles['body']
    ))
    story.append(Spacer(1, 4))

    query_block(story, styles, 48,
        'Create the Database',
        'Creates the database if it does not already exist. '
        'IF NOT EXISTS prevents an error when run multiple times.',
        ['CREATE DATABASE IF NOT EXISTS gym_membership_db',
         'CHARACTER SET utf8mb4',
         'COLLATE utf8mb4_unicode_ci'],
        returns='Query OK.',
        notes='utf8mb4 supports full Unicode including emoji. Always prefer utf8mb4 over utf8 in MySQL.',
        tag_text='SETUP', tag_color=colors.HexColor('#7c3aed'),
        used_in='setup.php, seed.php'
    )
    query_block(story, styles, 49,
        'Verify Foreign Key Constraints',
        'Queries the information_schema to confirm that all foreign keys are correctly '
        'set up after table creation. Used in the diagnostic report in seed.php.',
        ['SELECT TABLE_NAME, COLUMN_NAME,',
         '  REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME',
         'FROM information_schema.KEY_COLUMN_USAGE',
         "WHERE REFERENCED_TABLE_SCHEMA = 'gym_membership_db'",
         'ORDER BY TABLE_NAME'],
        returns='One row per foreign key: table, column, referenced table and column.',
        tag_text='DIAGNOSTIC', tag_color=colors.HexColor('#0891b2'),
        used_in='seed.php — FK verification step'
    )
    query_block(story, styles, 50,
        'Truncate All Tables for Re-seeding',
        'Clears all data from every table in dependency-safe order. '
        'FOREIGN_KEY_CHECKS=0 temporarily disables FK enforcement to allow '
        'TRUNCATE in any order.',
        ['SET FOREIGN_KEY_CHECKS = 0;',
         'TRUNCATE TABLE TRAINER_REQUEST;',
         'TRUNCATE TABLE PAYMENT;',
         'TRUNCATE TABLE ATTENDANCE;',
         'TRUNCATE TABLE ENROLLMENT;',
         'TRUNCATE TABLE CLASS;',
         'TRUNCATE TABLE MEMBER;',
         'TRUNCATE TABLE TRAINER;',
         'TRUNCATE TABLE MEMBERSHIP_PLAN;',
         'SET FOREIGN_KEY_CHECKS = 1;'],
        returns='Query OK for each statement.',
        notes='TRUNCATE is faster than DELETE for full table clears — it resets AUTO_INCREMENT counters too. Only use during development/testing.',
        tag_text='SETUP', tag_color=colors.HexColor('#7c3aed'),
        used_in='seed.php — re-seed wipe step'
    )

    # ── SUMMARY TABLE ──────────────────────────────────────────
    story.append(PageBreak())
    story.append(Paragraph('Query Summary', styles['chapter']))
    divider(story)

    headers = [['#', 'Query Name', 'Type', 'Table(s)', 'Used In']]
    summary = [
        ['1',  'Total Members Count',                'READ',       'MEMBER',                         'index.php'],
        ['2',  'Active Members Count',               'READ',       'MEMBER',                         'index.php'],
        ['3',  'Total Revenue',                      'READ',       'PAYMENT',                        'index.php'],
        ['4',  'Total Classes',                      'READ',       'CLASS',                          'index.php'],
        ['5',  'Total Trainers',                     'READ',       'TRAINER',                        'index.php'],
        ['6',  "Today's Attendance",                 'READ',       'ATTENDANCE',                     'index.php'],
        ['7',  'Pending Requests Count',             'READ',       'TRAINER_REQUEST',                'index.php'],
        ['8',  'Recent Registrations',               'READ',       'MEMBER + PLAN',                  'index.php'],
        ['9',  'Upcoming Classes',                   'READ',       'CLASS + TRAINER',                'index.php'],
        ['10', 'Expiring in 7 Days',                 'READ',       'MEMBER',                         'index.php'],
        ['11', 'Plans with Member Count',            'READ',       'MEMBERSHIP_PLAN + MEMBER',       'plans.php'],
        ['12', 'Create Plan',                        'CREATE',     'MEMBERSHIP_PLAN',                'plan_add.php'],
        ['13', 'Update Plan',                        'UPDATE',     'MEMBERSHIP_PLAN',                'plan_edit.php'],
        ['14', 'Delete Plan',                        'DELETE',     'MEMBER + MEMBERSHIP_PLAN',       'plan_delete.php'],
        ['15', 'Get Plan Duration',                  'READ',       'MEMBERSHIP_PLAN',                'member_add/edit.php'],
        ['16', 'List Members (Filtered)',             'READ',       'MEMBER + PLAN',                  'members.php'],
        ['17', 'NIC Uniqueness Check',               'VALIDATE',   'MEMBER',                         'member_add.php'],
        ['18', 'Insert Member',                      'CREATE',     'MEMBER',                         'member_add.php'],
        ['19', 'Update Member',                      'UPDATE',     'MEMBER',                         'member_edit.php'],
        ['20', 'Delete Member (Cascade)',             'DELETE',     'MEMBER + 4 tables',              'member_delete.php'],
        ['21', 'Member Full Profile',                'READ',       'MEMBER + PLAN',                  'member_view.php'],
        ['22', 'Member Enrolled Classes',            'READ',       'ENROLLMENT + CLASS',             'member_view.php'],
        ['23', 'Trainers with Aggregates',           'READ',       'TRAINER + CLASS + PAYMENT',      'trainers.php'],
        ['24', 'Insert Trainer',                     'CREATE',     'TRAINER',                        'trainer_add.php'],
        ['25', 'Auto-calculate Salary',              'READ',       'TRAINER + CLASS',                'payment_add.php'],
        ['26', 'Delete Trainer',                     'DELETE',     'TRAINER + 3 tables',             'trainer_delete.php'],
        ['27', 'List Classes with Enrolled Count',  'READ',       'CLASS + TRAINER + ENROLLMENT',   'classes.php'],
        ['28', 'Classes with Available Spots',       'READ',       'CLASS + ENROLLMENT',             'enrollment_add.php'],
        ['29', 'Insert Class',                       'CREATE',     'CLASS',                          'class_add.php'],
        ['30', 'Delete Class',                       'DELETE',     'CLASS + ENROLLMENT + ATTENDANCE','class_delete.php'],
        ['31', 'List Enrollments',                   'READ',       'ENROLLMENT + MEMBER + CLASS',    'enrollments.php'],
        ['32', 'Duplicate Enrollment Check',         'VALIDATE',   'ENROLLMENT',                     'enrollment_add.php'],
        ['33', 'Insert Enrollment',                  'CREATE',     'ENROLLMENT',                     'enrollment_add.php'],
        ['34', 'Delete Enrollment',                  'DELETE',     'ENROLLMENT',                     'enrollment_delete.php'],
        ['35', 'List Attendance (Filtered)',          'READ',       'ATTENDANCE + MEMBER + CLASS',    'attendance.php'],
        ['36', 'Attendance Rate Counts',             'READ',       'ATTENDANCE',                     'attendance.php'],
        ['37', 'Load Members for Marking',           'READ',       'ENROLLMENT + MEMBER + ATTENDANCE','attendance_mark.php'],
        ['38', 'Upsert Attendance',                  'UPSERT',     'ATTENDANCE',                     'attendance_mark.php'],
        ['39', 'List Payments (Filtered)',            'READ',       'PAYMENT + MEMBER + TRAINER',     'payments.php'],
        ['40', 'Payment Totals by Type',             'READ',       'PAYMENT',                        'payments.php'],
        ['41', 'Insert Membership Payment',          'CREATE',     'PAYMENT',                        'payment_add.php'],
        ['42', 'Insert Salary Payment',              'CREATE',     'PAYMENT',                        'payment_add.php'],
        ['43', 'List Requests (Filtered)',            'READ',       'TRAINER_REQUEST + MEMBER + TRAINER','trainer_requests.php'],
        ['44', 'Count Requests by Status',           'READ',       'TRAINER_REQUEST',                'trainer_requests.php'],
        ['45', 'Insert Trainer Request',             'CREATE',     'TRAINER_REQUEST',                'request_add.php'],
        ['46', 'Accept / Reject Request',            'UPDATE',     'TRAINER_REQUEST',                'request_update.php'],
        ['47', 'Delete Request',                     'DELETE',     'TRAINER_REQUEST',                'request_delete.php'],
        ['48', 'Create Database',                    'SETUP',      '—',                              'setup.php'],
        ['49', 'Verify Foreign Keys',                'DIAGNOSTIC', 'information_schema',             'seed.php'],
        ['50', 'Truncate All Tables',                'SETUP',      'All 8 tables',                   'seed.php'],
    ]

    type_colors = {
        'READ':       BLUE,
        'CREATE':     GREEN,
        'UPDATE':     AMBER,
        'DELETE':     RED,
        'VALIDATE':   colors.HexColor('#f97316'),
        'UPSERT':     colors.HexColor('#8b5cf6'),
        'SETUP':      colors.HexColor('#7c3aed'),
        'DIAGNOSTIC': colors.HexColor('#0891b2'),
    }

    # Build rows with coloured type badges
    table_data = []
    for row in summary:
        num, name, typ, tables, used = row
        badge_color = type_colors.get(typ, BLUE)
        type_para = Paragraph(
            f'<font color="white"><b>{typ}</b></font>',
            ParagraphStyle('tp', fontName='Helvetica-Bold', fontSize=7,
                           textColor=WHITE, leading=10, alignment=TA_CENTER)
        )
        table_data.append([
            Paragraph(num,    ParagraphStyle('sn', fontName='Helvetica-Bold', fontSize=8, textColor=MUTED, leading=11, alignment=TA_CENTER)),
            Paragraph(name,   ParagraphStyle('sname', fontName='Helvetica', fontSize=8, textColor=TEXT, leading=11)),
            type_para,
            Paragraph(tables, ParagraphStyle('stbl', fontName='Helvetica', fontSize=7.5, textColor=MUTED, leading=11)),
            Paragraph(used,   ParagraphStyle('su', fontName='Courier', fontSize=7.5, textColor=colors.HexColor('#1a6090'), leading=11)),
        ])

    col_w = [avail * p for p in [0.05, 0.30, 0.12, 0.28, 0.25]]
    full_data = [
        [Paragraph(h, ParagraphStyle('th', fontName='Helvetica-Bold', fontSize=8,
                   textColor=WHITE, leading=11, alignment=TA_CENTER))
         for h in ['#', 'Query Name', 'Type', 'Table(s)', 'Used In']]
    ] + table_data

    ts = Table(full_data, colWidths=col_w, repeatRows=1)
    style_cmds = [
        ('BACKGROUND',    (0,0), (-1,0),   NAVY),
        ('FONTSIZE',      (0,0), (-1,-1),  8),
        ('TOPPADDING',    (0,0), (-1,-1),  5),
        ('BOTTOMPADDING', (0,0), (-1,-1),  5),
        ('LEFTPADDING',   (0,0), (-1,-1),  6),
        ('RIGHTPADDING',  (0,0), (-1,-1),  6),
        ('GRID',          (0,0), (-1,-1),  0.4, BORDER),
        ('VALIGN',        (0,0), (-1,-1),  'MIDDLE'),
        ('ALIGN',         (0,0), (0,-1),   'CENTER'),
        ('ALIGN',         (2,0), (2,-1),   'CENTER'),
        ('ROWBACKGROUNDS',(0,1), (-1,-1),  [WHITE, LIGHT_BG]),
    ]
    # Colour the Type column cells
    for i, row in enumerate(summary):
        typ = row[2]
        bg = type_colors.get(typ, BLUE)
        style_cmds.append(('BACKGROUND', (2, i+1), (2, i+1), bg))

    ts.setStyle(TableStyle(style_cmds))
    story.append(ts)

    # ── Build PDF ──────────────────────────────────────────────
    doc.build(story,
              onFirstPage=on_first_page,
              onLaterPages=on_later_pages)
    print(f'PDF saved: {out}')

if __name__ == '__main__':
    build()
