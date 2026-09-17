"""Generate the GymPro V2 university-project technical documentation PDF.

Run from any directory:
    python v2/docs/generate_v2_documentation.py

Output:
    v2/docs/GymPro_V2_Technical_Documentation.pdf
"""

from __future__ import annotations

from datetime import date
from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase.pdfmetrics import stringWidth
from reportlab.platypus import (
    Flowable,
    HRFlowable,
    KeepTogether,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

SCRIPT_DIR = Path(__file__).resolve().parent
OUTPUT_PATH = SCRIPT_DIR / "GymPro_V2_Technical_Documentation.pdf"

PAGE_W, PAGE_H = A4
MARGIN = 18 * mm
CONTENT_W = PAGE_W - 2 * MARGIN

NAVY = colors.HexColor("#0D1B2A")
NAVY_MID = colors.HexColor("#162B40")
BLUE = colors.HexColor("#3A86FF")
CYAN = colors.HexColor("#38BDF8")
GREEN = colors.HexColor("#22C55E")
AMBER = colors.HexColor("#F59E0B")
RED = colors.HexColor("#EF4444")
PURPLE = colors.HexColor("#8B5CF6")
TEXT = colors.HexColor("#17263A")
MUTED = colors.HexColor("#52677F")
BORDER = colors.HexColor("#CCD8E4")
LIGHT = colors.HexColor("#F2F6FA")
LIGHT_BLUE = colors.HexColor("#EAF3FF")
CODE_BG = colors.HexColor("#102131")
CODE_TEXT = colors.HexColor("#CBE7F7")
WHITE = colors.white


def styles() -> dict[str, ParagraphStyle]:
    return {
        "cover_title": ParagraphStyle("cover_title", fontName="Helvetica-Bold", fontSize=32, leading=39, textColor=WHITE, alignment=TA_CENTER),
        "cover_sub": ParagraphStyle("cover_sub", fontName="Helvetica", fontSize=13, leading=19, textColor=colors.HexColor("#A9D9F5"), alignment=TA_CENTER),
        "cover_meta": ParagraphStyle("cover_meta", fontName="Helvetica", fontSize=9, leading=14, textColor=colors.HexColor("#82A7C2"), alignment=TA_CENTER),
        "chapter": ParagraphStyle("chapter", fontName="Helvetica-Bold", fontSize=17, leading=23, textColor=WHITE),
        "h2": ParagraphStyle("h2", fontName="Helvetica-Bold", fontSize=13, leading=17, textColor=NAVY, spaceBefore=11, spaceAfter=5),
        "h3": ParagraphStyle("h3", fontName="Helvetica-Bold", fontSize=10.5, leading=14, textColor=BLUE, spaceBefore=8, spaceAfter=3),
        "body": ParagraphStyle("body", fontName="Helvetica", fontSize=9.2, leading=14, textColor=TEXT, alignment=TA_JUSTIFY, spaceAfter=5),
        "body_small": ParagraphStyle("body_small", fontName="Helvetica", fontSize=8, leading=11.5, textColor=TEXT),
        "bullet": ParagraphStyle("bullet", fontName="Helvetica", fontSize=9, leading=13.5, textColor=TEXT, leftIndent=13, bulletIndent=3, spaceAfter=2),
        "table_head": ParagraphStyle("table_head", fontName="Helvetica-Bold", fontSize=7.6, leading=10, textColor=WHITE, alignment=TA_LEFT),
        "table": ParagraphStyle("table", fontName="Helvetica", fontSize=7.6, leading=10.5, textColor=TEXT),
        "table_small": ParagraphStyle("table_small", fontName="Helvetica", fontSize=6.7, leading=9, textColor=TEXT),
        "code": ParagraphStyle("code", fontName="Courier", fontSize=7.6, leading=11, textColor=CODE_TEXT),
        "toc": ParagraphStyle("toc", fontName="Helvetica", fontSize=9.4, leading=14, textColor=TEXT),
        "caption": ParagraphStyle("caption", fontName="Helvetica-Oblique", fontSize=7.5, leading=10, textColor=MUTED, alignment=TA_CENTER),
        "callout": ParagraphStyle("callout", fontName="Helvetica", fontSize=8.6, leading=13, textColor=TEXT),
        "footer": ParagraphStyle("footer", fontName="Helvetica", fontSize=7, textColor=MUTED),
    }


S = styles()


class DiagramBox(Flowable):
    """A compact horizontal flow diagram with boxes and arrows."""

    def __init__(self, labels: list[str], width: float = CONTENT_W, color=BLUE):
        super().__init__()
        self.labels = labels
        self.width = width
        self.color = color
        self.height = 35 * mm

    def wrap(self, avail_width, avail_height):
        return min(self.width, avail_width), self.height

    def draw(self):
        canvas = self.canv
        count = len(self.labels)
        gap = 10
        box_width = (self.width - gap * (count - 1)) / count
        box_height = 17 * mm
        y = 8 * mm
        for index, label in enumerate(self.labels):
            x = index * (box_width + gap)
            canvas.setFillColor(LIGHT_BLUE if index % 2 == 0 else LIGHT)
            canvas.setStrokeColor(self.color)
            canvas.setLineWidth(1)
            canvas.roundRect(x, y, box_width, box_height, 4, fill=1, stroke=1)
            canvas.setFillColor(NAVY)
            canvas.setFont("Helvetica-Bold", 7.2)
            words = label.split()
            lines: list[str] = []
            current = ""
            for word in words:
                candidate = (current + " " + word).strip()
                if stringWidth(candidate, "Helvetica-Bold", 7.2) <= box_width - 8:
                    current = candidate
                else:
                    if current:
                        lines.append(current)
                    current = word
            if current:
                lines.append(current)
            baseline = y + box_height / 2 + (len(lines) - 1) * 4
            for line_number, line in enumerate(lines):
                canvas.drawCentredString(x + box_width / 2, baseline - line_number * 9, line)
            if index < count - 1:
                x1 = x + box_width + 1
                x2 = x + box_width + gap - 1
                arrow_y = y + box_height / 2
                canvas.setStrokeColor(MUTED)
                canvas.line(x1, arrow_y, x2, arrow_y)
                canvas.line(x2 - 4, arrow_y + 3, x2, arrow_y)
                canvas.line(x2 - 4, arrow_y - 3, x2, arrow_y)


class RelationshipDiagram(Flowable):
    """A native PDF overview of the V2 entity relationships."""

    def __init__(self, width: float = CONTENT_W):
        super().__init__()
        self.width = width
        self.height = 112 * mm

    def wrap(self, avail_width, avail_height):
        return min(self.width, avail_width), self.height

    def draw(self):
        c = self.canv
        nodes = {
            "USER_ACCOUNT": (0.34, 0.86),
            "MEMBER": (0.12, 0.65),
            "TRAINER": (0.70, 0.65),
            "MEMBERSHIP_PLAN": (0.00, 0.43),
            "MEMBERSHIP": (0.25, 0.43),
            "GYM_CLASS": (0.67, 0.43),
            "MEMBERSHIP_PAYMENT": (0.17, 0.16),
            "CLASS_ENROLLMENT": (0.48, 0.20),
            "CLASS_ATTENDANCE": (0.75, 0.06),
            "GYM_VISIT": (0.02, 0.05),
        }
        box_w = 42 * mm
        box_h = 11 * mm
        coords: dict[str, tuple[float, float]] = {}
        for name, (nx, ny) in nodes.items():
            x = nx * (self.width - box_w)
            y = ny * (self.height - box_h)
            coords[name] = (x, y)
        edges = [
            ("USER_ACCOUNT", "MEMBER", "1 : 0..1"),
            ("USER_ACCOUNT", "TRAINER", "1 : 0..1"),
            ("MEMBERSHIP_PLAN", "MEMBERSHIP", "1 : many"),
            ("MEMBER", "MEMBERSHIP", "1 : many"),
            ("MEMBERSHIP", "MEMBERSHIP_PAYMENT", "1 : many"),
            ("TRAINER", "GYM_CLASS", "1 : many"),
            ("MEMBER", "CLASS_ENROLLMENT", "1 : many"),
            ("GYM_CLASS", "CLASS_ENROLLMENT", "1 : many"),
            ("CLASS_ENROLLMENT", "CLASS_ATTENDANCE", "1 : 0..1"),
            ("MEMBER", "GYM_VISIT", "1 : many"),
        ]
        c.setStrokeColor(colors.HexColor("#91A7BA"))
        c.setFillColor(MUTED)
        c.setFont("Helvetica", 5.8)
        for source, target, cardinality in edges:
            sx, sy = coords[source]
            tx, ty = coords[target]
            x1, y1 = sx + box_w / 2, sy + box_h / 2
            x2, y2 = tx + box_w / 2, ty + box_h / 2
            c.line(x1, y1, x2, y2)
            c.drawCentredString((x1 + x2) / 2, (y1 + y2) / 2 + 2, cardinality)
        for index, (name, (x, y)) in enumerate(coords.items()):
            c.setFillColor(NAVY if name in {"USER_ACCOUNT", "MEMBER", "TRAINER"} else NAVY_MID)
            c.setStrokeColor(BLUE if index % 2 == 0 else CYAN)
            c.roundRect(x, y, box_w, box_h, 4, fill=1, stroke=1)
            c.setFillColor(WHITE)
            c.setFont("Helvetica-Bold", 7)
            c.drawCentredString(x + box_w / 2, y + box_h / 2 - 2.5, name)


def p(text: str, style: str = "body") -> Paragraph:
    return Paragraph(text, S[style])


def bullet(text: str) -> Paragraph:
    return Paragraph(text, S["bullet"], bulletText="•")


def heading(text: str, level: int = 2) -> Paragraph:
    return p(text, "h2" if level == 2 else "h3")


def chapter(story: list, number: int, title: str, subtitle: str) -> None:
    story.append(PageBreak())
    block = Table([[p(f"{number}. {escape(title)}", "chapter")]], colWidths=[CONTENT_W])
    block.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), NAVY),
        ("LINEABOVE", (0, 0), (-1, 0), 4, BLUE),
        ("LEFTPADDING", (0, 0), (-1, -1), 12),
        ("RIGHTPADDING", (0, 0), (-1, -1), 12),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
    ]))
    story.extend([block, Spacer(1, 7), p(subtitle), Spacer(1, 3)])


def styled_table(headers: list[str], rows: list[list[str]], widths: list[float] | None = None, small: bool = False) -> Table:
    style_name = "table_small" if small else "table"
    data = [[p(escape(str(value)), "table_head") for value in headers]]
    for row in rows:
        data.append([p(escape(str(value)).replace("\n", "<br/>"), style_name) for value in row])
    table = Table(data, colWidths=widths, repeatRows=1, hAlign="LEFT")
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, LIGHT]),
        ("GRID", (0, 0), (-1, -1), 0.35, BORDER),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ]))
    return table


def callout(title: str, text: str, color=BLUE) -> Table:
    content = p(f"<b>{escape(title)}</b><br/>{escape(text)}", "callout")
    table = Table([[content]], colWidths=[CONTENT_W])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), LIGHT_BLUE),
        ("LINEBEFORE", (0, 0), (0, -1), 4, color),
        ("BOX", (0, 0), (-1, -1), 0.4, BORDER),
        ("LEFTPADDING", (0, 0), (-1, -1), 10),
        ("RIGHTPADDING", (0, 0), (-1, -1), 10),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
    ]))
    return table


def code_block(lines: list[str]) -> Table:
    body = "<br/>".join(escape(line).replace(" ", "&nbsp;") for line in lines)
    table = Table([[p(body, "code")]], colWidths=[CONTENT_W])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), CODE_BG),
        ("LINEBEFORE", (0, 0), (0, -1), 4, BLUE),
        ("LEFTPADDING", (0, 0), (-1, -1), 10),
        ("RIGHTPADDING", (0, 0), (-1, -1), 10),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
    ]))
    return table


def table_section(story: list, number: str, name: str, purpose: str, key: str, relationships: str, controls: list[str]) -> None:
    items = [heading(f"{number} {escape(name)}"), p(purpose)]
    items.append(styled_table(["Aspect", "Explanation"], [["Primary identity", key], ["Relationships", relationships]], [38 * mm, CONTENT_W - 38 * mm]))
    items.append(Spacer(1, 4))
    for control in controls:
        items.append(bullet(control))
    items.append(Spacer(1, 5))
    story.append(KeepTogether(items))


def first_page(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(NAVY)
    canvas.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
    canvas.setFillColor(BLUE)
    canvas.rect(0, PAGE_H - 7, PAGE_W, 7, fill=1, stroke=0)
    canvas.setFillColor(NAVY_MID)
    canvas.rect(0, 0, PAGE_W, 30 * mm, fill=1, stroke=0)
    canvas.setFillColor(colors.HexColor("#8BAEC7"))
    canvas.setFont("Helvetica", 8)
    canvas.drawCentredString(PAGE_W / 2, 10 * mm, f"Generated {date.today().strftime('%d %B %Y')}  |  University Project Documentation")
    canvas.restoreState()


def later_pages(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(NAVY)
    canvas.rect(0, PAGE_H - 13 * mm, PAGE_W, 13 * mm, fill=1, stroke=0)
    canvas.setFillColor(BLUE)
    canvas.rect(0, PAGE_H - 13 * mm, 4, 13 * mm, fill=1, stroke=0)
    canvas.setFillColor(WHITE)
    canvas.setFont("Helvetica-Bold", 8)
    canvas.drawString(MARGIN, PAGE_H - 8 * mm, "GymPro V2  |  Technical Documentation")
    canvas.setFillColor(LIGHT)
    canvas.rect(0, 0, PAGE_W, 10 * mm, fill=1, stroke=0)
    canvas.setStrokeColor(BORDER)
    canvas.line(0, 10 * mm, PAGE_W, 10 * mm)
    canvas.setFillColor(MUTED)
    canvas.setFont("Helvetica", 7.2)
    canvas.drawString(MARGIN, 3.5 * mm, "PHP 8.1+  •  MariaDB 10.5+ / MySQL 8.0+")
    canvas.drawRightString(PAGE_W - MARGIN, 3.5 * mm, f"Page {doc.page}")
    canvas.restoreState()


def build_story() -> list:
    story: list = []

    # Cover
    story.extend([
        Spacer(1, 48 * mm),
        p("GymPro V2", "cover_title"),
        Spacer(1, 6),
        Paragraph("Technical Documentation", ParagraphStyle("cover_second", parent=S["cover_title"], fontSize=21, leading=27, textColor=BLUE)),
        Spacer(1, 12),
        p("Database Design, System Architecture,<br/>Code Implementation and Verification", "cover_sub"),
        Spacer(1, 27 * mm),
    ])
    cover_info = [
        ["Project type", "University gym-management system"],
        ["Architecture", "Server-rendered PHP with service layer"],
        ["Database", "10 business tables + migration metadata"],
        ["Roles", "Administrator, Receptionist, Trainer, Member"],
        ["Document version", "V2 technical reference • September 2026"],
    ]
    cover_table = Table([[p(escape(a), "table_head"), p(escape(b), "table") ] for a, b in cover_info], colWidths=[42 * mm, CONTENT_W - 42 * mm])
    cover_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (0, -1), colors.HexColor("#10263A")),
        ("BACKGROUND", (1, 0), (1, -1), colors.HexColor("#152E43")),
        ("TEXTCOLOR", (0, 0), (-1, -1), WHITE),
        ("GRID", (0, 0), (-1, -1), 0.3, colors.HexColor("#29465E")),
        ("LEFTPADDING", (0, 0), (-1, -1), 9),
        ("RIGHTPADDING", (0, 0), (-1, -1), 9),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]))
    story.append(cover_table)

    # TOC
    story.extend([PageBreak(), heading("Table of Contents"), HRFlowable(width="100%", thickness=1.2, color=BLUE), Spacer(1, 7)])
    toc = [
        (1, "Introduction and Scope"), (2, "Requirements, Roles and Technology"),
        (3, "System Architecture"), (4, "Database Design and Relationships"),
        (5, "Table-by-Table Explanation"), (6, "Database Integrity Decisions"),
        (7, "Bootstrap and Shared Infrastructure"), (8, "Authentication and Authorization"),
        (9, "Membership and Payment Implementation"), (10, "Class and Enrollment Implementation"),
        (11, "Attendance and Gym Visit Implementation"), (12, "Role-Specific Web Pages"),
        (13, "Migration, Demo Data and Testing"), (14, "Security Controls"),
        (15, "Limitations and Future Work"), (16, "Installation and Verification"),
        (17, "Traceability and Source Appendix"),
    ]
    for number, title in toc:
        story.append(p(f"<b>{number:02d}</b>&nbsp;&nbsp;&nbsp;{escape(title)}", "toc"))

    chapter(story, 1, "Introduction and Scope", "This chapter establishes the purpose, educational context, and intentionally focused boundary of GymPro V2.")
    story.append(p("GymPro V2 is a role-based gym-management information system implemented as an isolated application inside the repository’s <b>v2/</b> directory. It demonstrates how a normalized relational database, transactional PHP services, server-rendered pages, and security-focused shared helpers can support common gym workflows."))
    story.append(heading("Project objectives"))
    for item in [
        "Maintain secure accounts and separate workspaces for administrators, receptionists, trainers, and members.",
        "Represent member and trainer profiles without duplicating authentication credentials.",
        "Track membership plans, purchased memberships, and simulated payment records.",
        "Support class creation, administrative review, member requests, trainer decisions, and attendance.",
        "Record physical gym entry and exit independently from class attendance.",
        "Demonstrate database integrity through keys, constraints, transactions, checksums, and contract tests.",
    ]:
        story.append(bullet(item))
    story.append(callout("Scope boundary", "V2 contains exactly ten business tables. SCHEMA_MIGRATION is an additional infrastructure table and is not counted as a business entity."))
    story.append(heading("Intentionally excluded"))
    story.append(p("Messaging, notifications, trainer qualification-document uploads, commissions and payouts, audit-log tables, scheduled-job infrastructure, and password-reset/email-verification token tables are outside the V2 scope. The payment workflow is a local simulation rather than a connection to a financial provider."))

    chapter(story, 2, "Requirements, Roles and Technology", "The application uses a small conventional stack that can be installed through XAMPP and explained clearly in an academic setting.")
    story.append(styled_table(["Component", "Selection", "Purpose"], [
        ["Server language", "PHP 8.1+", "Strictly typed application, services, pages and CLI tools"],
        ["Database", "MariaDB 10.5+ / MySQL 8.0+", "InnoDB transactions, constraints and utf8mb4 storage"],
        ["Data access", "mysqli", "Prepared statements and explicit transactions"],
        ["Presentation", "HTML + CSS", "Responsive server-rendered interface"],
        ["Enhancement", "Vanilla JavaScript", "Navigation, alerts, loading state and password visibility"],
        ["Local environment", "XAMPP on Windows", "Apache, PHP and MariaDB development runtime"],
    ], [34 * mm, 45 * mm, CONTENT_W - 79 * mm]))
    story.append(heading("Role responsibility matrix"))
    story.append(styled_table(["Role", "Primary capabilities", "Workspace"], [
        ["ADMIN", "Dashboard reporting; member, plan, trainer, payment and staff visibility; draft-class review", "admin/"],
        ["RECEPTIONIST", "Member facility check-in/check-out and visit-history review", "reception/"],
        ["TRAINER", "Create classes, decide pending enrollments and mark assigned-class attendance", "trainer/"],
        ["MEMBER", "Register, select membership, record simulated payment, request classes and view history", "member/"],
    ], [27 * mm, 105 * mm, CONTENT_W - 132 * mm]))
    story.append(callout("Authorization model", "Each role is guarded separately. An administrator is not automatically granted receptionist or trainer routes.", AMBER))

    chapter(story, 3, "System Architecture", "GymPro V2 separates HTTP/CLI entry points, domain services, shared infrastructure, and persistence responsibilities.")
    story.append(DiagramBox(["Browser or CLI", "PHP entry point", "Application service", "Database helpers", "MariaDB / MySQL"]))
    story.append(heading("Layer responsibilities"))
    story.append(styled_table(["Layer", "Representative files", "Responsibility"], [
        ["Entry points", "auth/, admin/, member/, trainer/, reception/, cli/", "Receive requests, invoke guards/services, render or redirect"],
        ["Application services", "app/Services/*.php", "Authorization-aware business rules and lifecycle transitions"],
        ["Shared infrastructure", "bootstrap/app.php, bootstrap/helpers.php, config/", "Environment, autoloading, sessions, CSRF, database and transactions"],
        ["Persistence", "database/migrations/001_create_core_schema.sql", "Durable entities, keys, checks, indexes and relationships"],
    ], [31 * mm, 62 * mm, CONTENT_W - 93 * mm]))
    story.append(heading("Typical state-changing request"))
    story.append(DiagramBox(["Role guard", "CSRF check", "Validate input", "Service transaction", "Flash + redirect"], color=GREEN))
    story.append(p("Pages do not concatenate submitted values into SQL. Values are bound through prepared statements. Multi-record transitions, such as inserting a payment and activating its membership, are wrapped in <b>transaction()</b> so either all changes commit or all are rolled back."))

    chapter(story, 4, "Database Design and Relationships", "The relational model connects identity, membership, scheduling, participation, and facility-access data while retaining a compact ten-table boundary.")
    story.append(RelationshipDiagram())
    story.append(p("Figure 1. Simplified entity-relationship overview. Actor references from USER_ACCOUNT to payment, attendance and visit records are omitted from some lines for visual clarity.", "caption"))
    story.append(heading("Domain grouping"))
    story.append(styled_table(["Domain", "Tables", "Purpose"], [
        ["Identity", "USER_ACCOUNT, MEMBER, TRAINER", "Credentials, roles and one-to-one profile types"],
        ["Membership", "MEMBERSHIP_PLAN, MEMBERSHIP, MEMBERSHIP_PAYMENT", "Catalog terms, purchased periods and financial records"],
        ["Scheduling", "GYM_CLASS", "Trainer-led sessions, timing, location, capacity and lifecycle"],
        ["Participation", "CLASS_ENROLLMENT, CLASS_ATTENDANCE", "Member requests, decisions and class participation"],
        ["Facility access", "GYM_VISIT", "Reception-controlled physical entry and exit"],
    ], [28 * mm, 68 * mm, CONTENT_W - 96 * mm]))
    story.append(heading("Relationship summary"))
    story.append(styled_table(["Parent", "Child", "Cardinality", "Meaning"], [
        ["USER_ACCOUNT", "MEMBER / TRAINER", "1 to 0..1", "An account may own one role-appropriate profile"],
        ["MEMBERSHIP_PLAN", "MEMBERSHIP", "1 to many", "A catalog plan can be purchased repeatedly"],
        ["MEMBER", "MEMBERSHIP", "1 to many", "Membership history is retained"],
        ["MEMBERSHIP", "MEMBERSHIP_PAYMENT", "1 to many", "Attempts or lifecycle payments belong to a membership"],
        ["TRAINER", "GYM_CLASS", "1 to many", "A trainer teaches classes"],
        ["MEMBER + GYM_CLASS", "CLASS_ENROLLMENT", "many to many", "Enrollment is the association entity"],
        ["CLASS_ENROLLMENT", "CLASS_ATTENDANCE", "1 to 0..1", "One final row per enrollment"],
        ["MEMBER", "GYM_VISIT", "1 to many", "A member accumulates visit history"],
    ], [31 * mm, 38 * mm, 24 * mm, CONTENT_W - 93 * mm], small=True))

    chapter(story, 5, "Table-by-Table Explanation", "Each table below is described in terms of its business purpose, identity, relationships, lifecycle and important safeguards.")
    table_section(story, "5.1", "USER_ACCOUNT", "Central authentication identity for every user. Credentials and role are stored once, while member/trainer details live in role-specific profile tables.", "UserAccountID (auto-increment); Email is unique.", "Optional one-to-one MEMBER or TRAINER profile; actor references from payments, attendance and visits.", [
        "Role is restricted to ADMIN, RECEPTIONIST, TRAINER or MEMBER.",
        "PasswordHash stores password_hash() output rather than plaintext.",
        "FailedLoginCount and LockedUntil support temporary account lockout.",
        "Status allows pending, active, suspended and disabled accounts without deleting history.",
    ])
    table_section(story, "5.2", "MEMBER", "Stores the member profile, public number, contact details, emergency contact and lifecycle state.", "MemberID; MemberNumber and UserAccountID are independently unique.", "Belongs to one MEMBER account; owns memberships, class enrollments and gym visits.", [
        "Separating the profile from USER_ACCOUNT keeps authentication fields normalized.",
        "JoinedOn records the business join date; Status supports non-destructive deactivation.",
        "Foreign-key deletion is restrictive to protect operational history.",
    ])
    table_section(story, "5.3", "TRAINER", "Stores the trainer’s public identity, specialization, biography, hire date and profile lifecycle.", "TrainerID; TrainerNumber and UserAccountID are independently unique.", "Belongs to one TRAINER account; may be assigned to many gym classes.", [
        "Profile status is checked in addition to account status for trainer operations.",
        "TrainerID becomes NULL on a class if deletion were permitted, preserving class history.",
    ])
    table_section(story, "5.4", "MEMBERSHIP_PLAN", "Defines reusable catalog terms offered to members.", "MembershipPlanID; Name is unique.", "Referenced by many MEMBERSHIP records.", [
        "DurationMonths must be positive and Price must be non-negative.",
        "Currency is constrained to uppercase; ClassLimit is nullable for unlimited access.",
        "IsActive controls availability for new selection without removing historical references.",
    ])
    table_section(story, "5.5", "MEMBERSHIP", "Represents one member’s purchased entitlement over an inclusive date interval.", "MembershipID.", "Belongs to MEMBER and MEMBERSHIP_PLAN; receives MEMBERSHIP_PAYMENT rows.", [
        "PlanNameSnapshot, PriceSnapshot and CurrencySnapshot preserve purchase-time terms even if the plan changes later.",
        "EndsOn must be on or after StartsOn.",
        "Lifecycle values are PENDING, ACTIVE, PAUSED, CANCELLED and EXPIRED.",
    ])
    table_section(story, "5.6", "MEMBERSHIP_PAYMENT", "Stores a payment attempt/result associated with a membership.", "MembershipPaymentID; transaction reference and idempotency key are nullable unique identifiers.", "Belongs to MEMBERSHIP; optionally records the acting USER_ACCOUNT.", [
        "Amount must be positive and currency uppercase.",
        "A PAID row must have PaidAt.",
        "The idempotency key lets a repeated request return the existing payment instead of inserting another.",
        "No card number, CVV or expiry value is stored.",
    ])
    table_section(story, "5.7", "GYM_CLASS", "Represents a trainer-led scheduled session with timing, capacity, venue and lifecycle.", "GymClassID.", "Optionally belongs to TRAINER; owns CLASS_ENROLLMENT rows.", [
        "EndsAt must be later than StartsAt and Capacity must be positive.",
        "Statuses are DRAFT, SCHEDULED, CANCELLED and COMPLETED.",
        "Application logic checks trainer schedule overlap before insertion or approval.",
    ])
    table_section(story, "5.8", "CLASS_ENROLLMENT", "Association and workflow record connecting one member to one class.", "ClassEnrollmentID; (GymClassID, MemberID) is unique.", "Belongs to GYM_CLASS and MEMBER; may own one CLASS_ATTENDANCE row.", [
        "Statuses are WAITLISTED, ENROLLED and CANCELLED.",
        "The unique class/member pair prevents duplicate requests.",
        "CancelledAt is required whenever the row is CANCELLED.",
    ])
    table_section(story, "5.9", "CLASS_ATTENDANCE", "Stores the attendance outcome for an enrolled member in a specific class.", "ClassAttendanceID; ClassEnrollmentID is unique.", "Composite reference to CLASS_ENROLLMENT(ClassEnrollmentID, GymClassID); marked by USER_ACCOUNT.", [
        "Statuses are PRESENT, ABSENT, LATE and EXCUSED.",
        "PRESENT and LATE require CheckedInAt.",
        "The composite relationship prevents an attendance row from naming a class different from its enrollment.",
    ])
    table_section(story, "5.10", "GYM_VISIT", "Records physical facility entry and exit, independently of participation in a class.", "GymVisitID; generated OpenVisitMarker participates in a unique key.", "Belongs to MEMBER; check-in and checkout actors reference USER_ACCOUNT.", [
        "Checkout cannot occur before check-in and requires a checkout actor.",
        "OpenVisitMarker is 1 only while CheckedOutAt is NULL; uniqueness with MemberID permits one open visit per member.",
        "Completed visits retain both timestamps and reception actors for operational traceability.",
    ])
    story.append(heading("5.11 SCHEMA_MIGRATION (infrastructure)"))
    story.append(p("This table is not a domain entity. It stores each migration identifier, SHA-256 checksum, start/completion timestamps, elapsed milliseconds and dirty state. The migrator uses it to reject modified applied migrations and detect interrupted work."))

    chapter(story, 6, "Database Integrity Decisions", "Important rules are enforced at the lowest practical level: declaratively in the database where possible and transactionally in services where the rule depends on current business state.")
    story.append(styled_table(["Business rule", "Enforcement", "Mechanism"], [
        ["One login per email", "Database", "Unique Email key"],
        ["One profile per account/type", "Database", "Unique UserAccountID in profile"],
        ["One request per member/class", "Database", "Unique (GymClassID, MemberID)"],
        ["Attendance belongs to exact class", "Database", "Composite foreign key"],
        ["One open visit per member", "Database", "Generated marker + unique key"],
        ["Payment retry does not duplicate", "Database + service", "Unique idempotency key + locked lookup"],
        ["Membership covers class date", "Service", "Date-bounded ACTIVE membership query"],
        ["Trainer schedule does not overlap", "Service transaction", "Actor lock followed by overlap query"],
        ["Class capacity is available", "Service transaction", "Locked class plus enrolled count"],
        ["Attendance matches roster", "Service", "Exact sorted enrollment-ID comparison"],
    ], [61 * mm, 39 * mm, CONTENT_W - 100 * mm]))
    story.append(heading("Purchase snapshots"))
    story.append(DiagramBox(["Active plan", "Copy name / price / currency", "Membership snapshot", "Future plan edits do not rewrite purchase"], color=PURPLE))
    story.append(heading("Composite attendance identity"))
    story.append(code_block([
        "FOREIGN KEY (ClassEnrollmentID, GymClassID)",
        "    REFERENCES CLASS_ENROLLMENT (ClassEnrollmentID, GymClassID)",
    ]))
    story.append(p("Repeating GymClassID in CLASS_ATTENDANCE supports direct class reporting. The composite key ensures this denormalized convenience cannot contradict the enrollment’s class."))
    story.append(heading("One-open-visit invariant"))
    story.append(code_block([
        "OpenVisitMarker = CASE WHEN CheckedOutAt IS NULL THEN 1 ELSE NULL END",
        "UNIQUE (MemberID, OpenVisitMarker)",
    ]))
    story.append(p("MySQL/MariaDB unique keys allow multiple NULL values. Completed visits therefore coexist, while a second row with the same MemberID and marker 1 is rejected."))

    chapter(story, 7, "Bootstrap and Shared Infrastructure", "The lightweight bootstrap replaces a large framework while retaining centralized configuration, sessions, autoloading and database primitives.")
    story.append(styled_table(["Function / component", "Implementation purpose"], [
        ["bootstrap/app.php", "Defines V2_ROOT, loads helpers and .env, registers GymPro\\V2 autoloading, starts secure sessions and sends headers"],
        ["env(), env_bool(), env_int()", "Read and validate environment configuration"],
        ["config()", "Loads nested values from config/*.php with static caching"],
        ["base_url(), redirect()", "Build application URLs and perform HTTP redirects"],
        ["csrf_token(), csrf_field(), verify_csrf()", "Create session token, render hidden field and reject invalid mutations"],
        ["db(), db_all(), db_one(), db_execute()", "Open strict mysqli connection and execute prepared queries"],
        ["transaction()", "Begin, commit and rollback multi-query operations"],
        ["current_user(), require_*()", "Resolve active sessions and enforce guest/login/role boundaries"],
        ["flash(), flash_input()", "Carry messages and safe old form values across redirects"],
        ["e()", "Escape untrusted text for HTML output"],
    ], [58 * mm, CONTENT_W - 58 * mm]))
    story.append(heading("Session and response protections"))
    for item in [
        "Strict session mode and cookie-only sessions.",
        "HttpOnly and SameSite cookies; optional Secure cookies for HTTPS.",
        "Session ID regeneration after successful login.",
        "X-Content-Type-Options, X-Frame-Options, Referrer-Policy and Permissions-Policy headers.",
        "Existing sessions are revoked when the account is no longer ACTIVE.",
    ]:
        story.append(bullet(item))

    chapter(story, 8, "Authentication and Authorization", "AuthService implements member/trainer registration, role-aware login, lockout accounting, session establishment and logout.")
    story.append(styled_table(["Method", "Primary behavior", "Tables"], [
        ["registerMember(data)", "Validate fields; atomically create ACTIVE member account and profile", "USER_ACCOUNT, MEMBER"],
        ["registerTrainer(data)", "Validate fields; atomically create PENDING trainer account and profile", "USER_ACCOUNT, TRAINER"],
        ["login(email, password, expectedRole)", "Find role account, check lock, verify hash, reset failures and create session", "USER_ACCOUNT"],
        ["logout()", "Clear server session and expire session cookie", "—"],
    ], [49 * mm, 89 * mm, CONTENT_W - 138 * mm]))
    story.append(heading("Login flow"))
    story.append(DiagramBox(["Normalize email + role", "Load account", "Check temporary lock", "Verify password hash", "Regenerate session + redirect"], color=GREEN))
    story.append(p("For an unknown account, a dummy password hash is still verified to reduce obvious timing differences. Passwords are created with <b>password_hash()</b> and checked with <b>password_verify()</b>. The selected portal role is matched against the account role."))
    story.append(callout("Current activation limitation", "Trainer registration creates USER_ACCOUNT and TRAINER rows as PENDING, but the current administrator trainer page has no activation action. Activation therefore requires a future workflow rather than being fully usable through the V2 browser interface.", AMBER))

    chapter(story, 9, "Membership and Payment Implementation", "MembershipService controls plan selection, current membership lookup and atomic simulated payment activation.")
    story.append(heading("Selecting a plan"))
    story.append(DiagramBox(["Lock member profile", "Lock active plan", "Expire stale records", "Reject current/pending duplicate", "Insert PENDING snapshot"], color=PURPLE))
    story.append(p("The service computes an inclusive end date by adding the selected DurationMonths and subtracting one day. It copies plan name, price and currency into the membership so historical terms survive catalog changes."))
    story.append(heading("Recording a simulated payment"))
    story.append(DiagramBox(["Validate method/reference", "Check idempotency key", "Lock owned membership", "Insert PAID record", "Activate membership + commit"], color=GREEN))
    story.append(p("The transaction binds the payment to a membership owned by the acting member. An existing idempotency key returns its prior row; a successful insert and membership activation commit together."))
    story.append(callout("Demonstration payment only", "No payment gateway or trusted external confirmation is integrated. Current member-submitted form data directly records PAID and activates membership. This is suitable for local workflow demonstration, not real financial settlement.", RED))
    story.append(callout("Data minimization", "The application never requests or stores full card numbers, CVV values or card expiry dates.", GREEN))

    chapter(story, 10, "Class and Enrollment Implementation", "ClassService owns future class creation, administrative draft review, member enrollment requests and trainer decisions.")
    story.append(heading("Class lifecycle"))
    story.append(DiagramBox(["Trainer creates DRAFT", "Admin reviews", "SCHEDULED or CANCELLED", "Class occurs", "COMPLETED (schema state)"], color=CYAN))
    story.append(p("Creation validates name, description, location, capacity, start/end ordering and future timing. Locking the trainer actor serializes overlap checks for that trainer. Administrative approval verifies the class is still DRAFT, future-dated, assigned to an active trainer and not overlapping."))
    story.append(heading("Enrollment lifecycle"))
    story.append(DiagramBox(["Member requests", "WAITLISTED", "Trainer rechecks entitlement", "ENROLLED or CANCELLED"], color=AMBER))
    story.append(p("A member needs an ACTIVE membership whose date range covers the class. The trainer may decide only requests for their own future SCHEDULED class. Before approval, the service checks membership entitlement, class-limit consumption and enrolled capacity."))
    story.append(callout("Current lifecycle gaps", "Members cannot withdraw WAITLISTED or ENROLLED rows, trainers cannot cancel an already ENROLLED member, and no application action currently transitions a SCHEDULED class to COMPLETED. Review notes and enrollment reasons are accepted but not persisted by the ten-table schema.", AMBER))

    chapter(story, 11, "Attendance and Gym Visit Implementation", "AttendanceService deliberately separates participation in a class from physical access to the gym facility.")
    story.append(styled_table(["Concept", "CLASS_ATTENDANCE", "GYM_VISIT"], [
        ["Purpose", "Participation in an organized class", "Physical entry and exit"],
        ["Member link", "Through CLASS_ENROLLMENT", "Direct MEMBER foreign key"],
        ["Primary actor", "Assigned TRAINER account", "RECEPTIONIST account"],
        ["Time data", "Class check-in where relevant", "Facility CheckedInAt / CheckedOutAt"],
        ["Duplicate guard", "One row per enrollment", "One open visit per member"],
    ], [29 * mm, 65 * mm, CONTENT_W - 94 * mm]))
    story.append(heading("Class attendance algorithm"))
    story.append(DiagramBox(["Lock assigned class", "Reject future class", "Lock ENROLLED roster", "Compare exact submitted IDs", "Upsert statuses"], color=GREEN))
    story.append(p("Only the assigned active trainer can submit. The submitted key set must exactly equal the sorted enrolled roster, preventing omitted or injected enrollment IDs. PRESENT and LATE receive a check-in timestamp; ABSENT and EXCUSED do not."))
    story.append(heading("Reception check-in/out"))
    story.append(DiagramBox(["Validate receptionist", "Lock active member", "Confirm current membership", "Reject existing open visit", "Create or close visit"], color=BLUE))
    story.append(callout("Attendance history limitation", "Resubmission updates the existing attendance row, including for COMPLETED classes. No correction deadline or historical revision record is retained, so the current implementation should not be described as an immutable audit trail.", AMBER))

    chapter(story, 12, "Role-Specific Web Pages", "Thin PHP controllers use shared guards, services and templates to expose each role’s workflow.")
    story.append(styled_table(["Area", "Route", "Behavior"], [
        ["Public", "index.php", "Landing page and links to authentication"],
        ["Authentication", "auth/login.php", "Role selection, credential submission and dashboard redirect"],
        ["Authentication", "auth/register-member.php", "Self-service member registration"],
        ["Authentication", "auth/register-trainer.php", "Pending trainer application"],
        ["Member", "member/index.php", "Membership, class and attendance summary"],
        ["Member", "member/membership.php", "Current membership and plan selection"],
        ["Member", "member/checkout.php", "Simulated payment record submission"],
        ["Member", "member/classes.php", "Browse scheduled classes and request enrollment"],
        ["Member", "member/attendance.php", "Class participation history"],
        ["Trainer", "trainer/classes.php", "Create and list own classes"],
        ["Trainer", "trainer/enrollments.php", "Approve/cancel waitlisted requests"],
        ["Trainer", "trainer/attendance.php", "Mark exact enrolled roster"],
        ["Reception", "reception/index.php", "Check members in/out"],
        ["Reception", "reception/history.php", "Search visit history by member/date"],
        ["Admin", "admin/index.php", "Operational dashboard statistics"],
        ["Admin", "admin/classes.php", "Review DRAFT classes"],
        ["Admin", "admin/members.php, plans.php, trainers.php", "Directories and reporting views"],
        ["Admin", "admin/payments.php, staff.php", "Payment ledger and staff-account reporting"],
    ], [24 * mm, 62 * mm, CONTENT_W - 86 * mm], small=True))
    story.append(p("The shared includes/header.php builds role-specific navigation, while includes/footer.php loads the local JavaScript. assets/css/app.css provides the responsive application shell, forms, cards, alerts and tables. assets/js/app.js progressively enhances mobile navigation, alert dismissal, submission feedback and password visibility."))
    story.append(callout("Administrative scope", "Several administrator pages are read-only reporting directories rather than full create/read/update/delete modules. In particular, plan creation/editing and trainer activation are not currently exposed.", AMBER))

    chapter(story, 13, "Migration, Demo Data and Testing", "CLI tools make schema setup repeatable, protect migration history, and populate safe fictional demonstrations.")
    story.append(heading("Migration runner"))
    story.append(DiagramBox(["Acquire advisory lock", "Discover ordered SQL", "Verify checksum/state", "Mark dirty + execute", "Mark applied + release"], color=PURPLE))
    story.append(p("cli/migrate.php supports migration, status and dry-run modes. It stores SHA-256 checksums, refuses modified applied files, detects dirty records and prevents concurrent migration processes with a database advisory lock."))
    story.append(heading("Demo seeder"))
    for item in [
        "Runs only from CLI and refuses execution unless APP_ENV=local.",
        "Uses clearly fictional example.test accounts and the documented local password Demo@12345.",
        "Creates one starter scenario and five connected scenarios numbered 101 through 105.",
        "Exercises every business table while preserving the four canonical role values.",
        "Upserts only reserved markers inside one transaction; it never truncates unrelated data.",
    ]:
        story.append(bullet(item))
    story.append(heading("Schema contract"))
    story.append(p("tests/schema_contract.php statically parses the migration and verifies the exact table contract, required columns, keys, foreign keys, InnoDB and utf8mb4 declarations. When configured, it also queries INFORMATION_SCHEMA and can require live database validation."))
    story.append(styled_table(["Verification type", "Coverage", "Boundary"], [
        ["Static schema contract", "Migration structure and declared constraints", "Does not execute browser workflows"],
        ["Optional live metadata", "Actual table/column/constraint metadata", "Requires reachable configured database"],
        ["Manual workflow checklist", "Roles, CSRF, payment, classes, attendance and visits", "Not a broad automated suite"],
        ["Seeder rerun", "Reserved-row idempotency and relationship integrity", "Local fictional data only"],
    ], [40 * mm, 72 * mm, CONTENT_W - 112 * mm]))

    chapter(story, 14, "Security Controls", "V2 combines application-layer protections with database constraints, while avoiding claims of formal certification or complete production hardening.")
    story.append(styled_table(["Risk", "Implemented control", "Primary location"], [
        ["Credential disclosure", "password_hash() / password_verify(); no plaintext storage", "AuthService.php"],
        ["SQL injection", "Prepared mysqli statements with bound values", "helpers.php and services"],
        ["Cross-site request forgery", "Session token + hash_equals() on mutations", "helpers.php and forms"],
        ["Cross-site scripting", "Contextual HTML escaping through e()", "helpers.php and templates"],
        ["Session fixation", "Regenerate session identifier after login", "AuthService.php"],
        ["Stale account access", "current_user() rechecks ACTIVE state", "helpers.php"],
        ["Unauthorized routes", "require_guest/login/role guards", "helpers.php and entry points"],
        ["Repeated password guessing", "Account failure count and temporary lock timestamp", "AuthService.php / USER_ACCOUNT"],
        ["Partial multi-row updates", "Commit/rollback transaction helper", "helpers.php and services"],
        ["Duplicate financial submission", "Unique idempotency key and locked prior lookup", "MembershipService.php / schema"],
    ], [42 * mm, 85 * mm, CONTENT_W - 127 * mm], small=True))
    story.append(heading("Deployment considerations"))
    story.append(p("Production deployment should use HTTPS, SESSION_SECURE=true, a private APP_KEY, least-privileged database credentials, disabled debug output, backups, monitoring and web-server hardening. The local .env file is intentionally excluded from version control and from this document."))
    story.append(callout("Known input/error issues", "Some controllers cast attacker-controlled array values to strings, which can emit warnings. Public controllers also display raw Throwable messages, potentially exposing database details. These are documented defects, not security guarantees.", RED))

    chapter(story, 15, "Limitations and Future Work", "Transparent evaluation separates the implemented educational system from features required by a mature production service.")
    limitations = [
        ["Payment trust", "Member-controlled input immediately records PAID; integrate a trusted provider callback or staff-confirmed workflow."],
        ["Trainer activation", "Add an administrator action that atomically activates both account and trainer profile."],
        ["Plan administration", "Add validated administrator creation, editing and activation/deactivation operations."],
        ["Membership expiry", "Centralize expiry normalization so getCurrent() never displays an expired ACTIVE/PAUSED row."],
        ["Attendance history", "Introduce bounded corrections with retained reason/history within the chosen schema strategy."],
        ["Enrollment lifecycle", "Allow member withdrawal and trainer cancellation of accepted enrollments."],
        ["Class lifecycle", "Implement SCHEDULED to COMPLETED transition."],
        ["Lockout threshold", "Correct assignment ordering that currently locks on the fourth failure rather than the intended fifth."],
        ["Input handling", "Reject non-scalar request values before casting."],
        ["Error handling", "Expose safe domain messages and log internal exceptions privately."],
        ["Automated coverage", "Add service, integration, concurrency and browser tests plus CI."],
        ["Production services", "Add mail/recovery, monitoring, backup and operational procedures only if scope expands."],
    ]
    story.append(styled_table(["Area", "Current limitation / recommended improvement"], limitations, [42 * mm, CONTENT_W - 42 * mm]))
    story.append(p("Messaging, notifications, trainer qualification uploads, commissions/payouts, audit-log tables, scheduled jobs and reset/verification-token infrastructure remain intentionally excluded unless a future project revision changes the ten-table scope."))

    chapter(story, 16, "Installation and Verification", "The following sequence reproduces the V2 database and documentation in a standard local XAMPP environment.")
    story.append(heading("Application setup"))
    for number, instruction in enumerate([
        "Start Apache and MySQL from the XAMPP Control Panel.",
        "Copy v2/.env.example to v2/.env and set local database values.",
        "Create an empty utf8mb4 database named gym_system_v2.",
        "Run the migration command and inspect status.",
        "Optionally run the local-only fictional data seeder.",
        "Open the APP_URL value in a browser.",
    ], start=1):
        story.append(p(f"<b>{number}.</b> {escape(instruction)}"))
    story.append(code_block([
        r"C:\xampp\php\php.exe v2\cli\migrate.php",
        r"C:\xampp\php\php.exe v2\cli\migrate.php status",
        r"C:\xampp\php\php.exe v2\cli\seed-demo.php",
        r"C:\xampp\php\php.exe v2\tests\schema_contract.php",
    ]))
    story.append(heading("Regenerating this PDF"))
    story.append(code_block([
        "python -m pip install -r v2/docs/requirements-pdf.txt",
        "python v2/docs/generate_v2_documentation.py",
    ]))
    story.append(p("The generated file is v2/docs/GymPro_V2_Technical_Documentation.pdf. The generator resolves paths relative to itself and does not read .env or require a running web/database server."))

    chapter(story, 17, "Traceability and Source Appendix", "This matrix connects user-facing workflows to their entry points, services and principal persistent entities.")
    story.append(styled_table(["Workflow", "Page / command", "Service", "Principal tables"], [
        ["Member registration", "auth/register-member.php", "AuthService", "USER_ACCOUNT, MEMBER"],
        ["Trainer application", "auth/register-trainer.php", "AuthService", "USER_ACCOUNT, TRAINER"],
        ["Login/logout", "auth/login.php, logout.php", "AuthService", "USER_ACCOUNT"],
        ["Choose plan", "member/membership.php", "MembershipService", "MEMBERSHIP_PLAN, MEMBERSHIP"],
        ["Record payment", "member/checkout.php", "MembershipService", "MEMBERSHIP, MEMBERSHIP_PAYMENT"],
        ["Create/review class", "trainer/classes.php, admin/classes.php", "ClassService", "TRAINER, GYM_CLASS"],
        ["Request/decide enrollment", "member/classes.php, trainer/enrollments.php", "ClassService", "MEMBERSHIP, GYM_CLASS, CLASS_ENROLLMENT"],
        ["Mark class attendance", "trainer/attendance.php", "AttendanceService", "GYM_CLASS, CLASS_ENROLLMENT, CLASS_ATTENDANCE"],
        ["Facility check-in/out", "reception/index.php", "AttendanceService", "MEMBER, MEMBERSHIP, GYM_VISIT"],
        ["Install schema", "cli/migrate.php", "Migration runner", "SCHEMA_MIGRATION + business tables"],
    ], [42 * mm, 61 * mm, 38 * mm, CONTENT_W - 141 * mm], small=True))
    story.append(heading("Authoritative source files"))
    sources = [
        "database/migrations/001_create_core_schema.sql — canonical schema, statuses and constraints",
        "app/Services/AuthService.php — registration, authentication and lockout",
        "app/Services/MembershipService.php — plan selection and simulated payment activation",
        "app/Services/ClassService.php — class and enrollment workflows",
        "app/Services/AttendanceService.php — class attendance and facility visits",
        "bootstrap/app.php and bootstrap/helpers.php — runtime, sessions, CSRF, database and role guards",
        "admin/, member/, trainer/, reception/ and auth/ — HTTP controllers and views",
        "cli/migrate.php and cli/seed-demo.php — schema lifecycle and fictional data",
        "tests/schema_contract.php — static and optional live schema verification",
        "README.md and docs/*.md — setup, architecture, data dictionary, ERD, workflows and testing notes",
    ]
    for source in sources:
        story.append(bullet(source))
    story.append(callout("Conclusion", "GymPro V2 is a focused university-project implementation with a strong normalized schema, meaningful relational constraints, transactional services and clear role boundaries. Its limitations are documented so the system can be evaluated accurately and improved systematically.", GREEN))

    return story


def build() -> Path:
    document = SimpleDocTemplate(
        str(OUTPUT_PATH),
        pagesize=A4,
        leftMargin=MARGIN,
        rightMargin=MARGIN,
        topMargin=18 * mm,
        bottomMargin=15 * mm,
        title="GymPro V2 Technical Documentation",
        author="GymPro V2 Project",
        subject="Database design, architecture, code implementation and verification",
        keywords="GymPro V2, PHP, MariaDB, MySQL, gym management, university project",
    )
    document.build(build_story(), onFirstPage=first_page, onLaterPages=later_pages)
    return OUTPUT_PATH


if __name__ == "__main__":
    result = build()
    print(f"PDF saved: {result}")
