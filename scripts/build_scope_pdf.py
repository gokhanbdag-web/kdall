from reportlab.lib.pagesizes import A4
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.units import mm
from xml.sax.saxutils import escape
import os, re

SRC="docs/KodVadAll_Tum_Ozellikler.md"
OUT="docs/KodVadAll_Tum_Ozellikler.pdf"
font="/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
bold="/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
if os.path.exists(font):
    pdfmetrics.registerFont(TTFont("DV",font)); pdfmetrics.registerFont(TTFont("DVB",bold))
    F,B="DV","DVB"
else: F,B="Helvetica","Helvetica-Bold"
NAVY=colors.HexColor("#12233F"); BLUE=colors.HexColor("#2367D1")
CYAN=colors.HexColor("#36B7C8"); PALE=colors.HexColor("#EFF5FC")
INK=colors.HexColor("#25344A"); MUTED=colors.HexColor("#64748B")
s=getSampleStyleSheet()
s.add(ParagraphStyle(name="Cover",fontName=B,fontSize=29,leading=35,textColor=colors.white,spaceAfter=14))
s.add(ParagraphStyle(name="Sub",fontName=F,fontSize=11,leading=17,textColor=colors.HexColor("#D8E6F7")))
s.add(ParagraphStyle(name="H1x",fontName=B,fontSize=16,leading=21,textColor=NAVY,spaceBefore=9,spaceAfter=7))
s.add(ParagraphStyle(name="H2x",fontName=B,fontSize=10.2,leading=14,textColor=BLUE,spaceBefore=7,spaceAfter=4))
s.add(ParagraphStyle(name="Bodyx",fontName=F,fontSize=8.2,leading=12,textColor=INK,spaceAfter=3))
s.add(ParagraphStyle(name="Bulletx",fontName=F,fontSize=8,leading=11.5,leftIndent=12,firstLineIndent=-8,textColor=INK,spaceAfter=2))
s.add(ParagraphStyle(name="Smallx",fontName=F,fontSize=7.2,leading=10,textColor=MUTED))
text=open(SRC,encoding="utf-8").read().splitlines()
story=[]
story += [Spacer(1,36*mm),Paragraph("KODVADALL",ParagraphStyle("brandx",fontName=B,fontSize=13,textColor=CYAN,leading=16)),
          Spacer(1,18*mm),Paragraph("İşletmenizin<br/>tüm operasyonları<br/><font color='#36B7C8'>tek merkezde.</font>",s["Cover"]),
          Paragraph("ERP · CRM · E-TİCARET · PAZARYERİ · İŞLETME OPERASYONLARI",s["Sub"]),
          Spacer(1,17*mm),Paragraph("30 ANA KAPSAM BAŞLIĞI  |  360° İŞLETME GÖRÜNÜMÜ  |  TEK YÖNETİM MERKEZİ",ParagraphStyle("statx",fontName=B,fontSize=8,leading=12,textColor=colors.white)),
          Spacer(1,35*mm),Paragraph("ÜRÜN KAPSAM DOSYASI · 2026",ParagraphStyle("footx",fontName=B,fontSize=8,textColor=CYAN))]
story.append(PageBreak())
in_intro=False
for raw in text:
    line=raw.strip()
    if not line or line=="---": continue
    if line.startswith("# "):
        continue
    if line.startswith("## "):
        story.append(Paragraph(escape(line[3:]),s["H1x"]))
    elif line.startswith("> "):
        story.append(Paragraph(escape(line[2:]),s["Smallx"])); story.append(Spacer(1,3))
    elif line.startswith("- "):
        story.append(Paragraph("• "+escape(line[2:]),s["Bulletx"]))
    elif line.startswith("**") and line.endswith("**"):
        story.append(Paragraph("<b>"+escape(line.strip("*"))+"</b>",s["Bodyx"]))
    else:
        story.append(Paragraph(escape(line),s["Bodyx"]))
def page(canvas,doc):
    canvas.saveState(); w,h=A4
    if doc.page==1:
        canvas.setFillColor(NAVY); canvas.rect(0,0,w,h,fill=1,stroke=0)
        canvas.setFillColor(colors.HexColor("#18385E")); canvas.circle(w-10*mm,h-20*mm,42*mm,fill=1,stroke=0)
        canvas.setFillColor(colors.HexColor("#174E69")); canvas.circle(w-5*mm,18*mm,29*mm,fill=1,stroke=0)
    else:
        canvas.setFillColor(NAVY); canvas.rect(0,h-8*mm,w,8*mm,fill=1,stroke=0)
        canvas.setFont(F,7); canvas.setFillColor(MUTED)
        canvas.drawString(18*mm,10*mm,"KODVADALL  |  ÜRÜN KAPSAM DOSYASI")
        canvas.drawRightString(w-18*mm,10*mm,str(doc.page))
        canvas.setStrokeColor(colors.HexColor("#D9E3EF")); canvas.line(18*mm,14*mm,w-18*mm,14*mm)
    canvas.restoreState()
doc=SimpleDocTemplate(OUT,pagesize=A4,leftMargin=20*mm,rightMargin=20*mm,topMargin=17*mm,bottomMargin=19*mm,title="KodVadAll - Tüm Özellikler",author="KodVadAll")
doc.build(story,onFirstPage=page,onLaterPages=page)
print("Created",OUT,os.path.getsize(OUT))
