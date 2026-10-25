from pathlib import Path
from PIL import Image as PILImage

from reportlab.lib import colors
from reportlab.lib.colors import HexColor
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle,
    PageBreak, KeepTogether, HRFlowable, Image,
)


ROOT = Path(r'C:\Docker\plataforma_pinciara-teste_02')
CAPTURES = ROOT / 'tmp' / 'guide-demo' / 'captures'
ASSETS = ROOT / 'tmp' / 'pdfs' / 'guide-assets'
OUT = ROOT / 'output' / 'pdf' / 'manual-treinamento-plataforma-pinciara.pdf'
ASSETS.mkdir(parents=True, exist_ok=True)
OUT.parent.mkdir(parents=True, exist_ok=True)

pdfmetrics.registerFont(TTFont('Segoe', r'C:\Windows\Fonts\segoeui.ttf'))
pdfmetrics.registerFont(TTFont('Segoe-Bold', r'C:\Windows\Fonts\segoeuib.ttf'))
pdfmetrics.registerFont(TTFont('Segoe-Italic', r'C:\Windows\Fonts\segoeuii.ttf'))

INK = HexColor('#172536')
NAVY = HexColor('#17324d')
TEAL = HexColor('#0f766e')
BLUE = HexColor('#0879a8')
MUTED = HexColor('#526477')
LINE = HexColor('#dbe4ea')
PALE = HexColor('#eef5f6')
PALE_BLUE = HexColor('#edf5f9')
PALE_GOLD = HexColor('#faf5e9')
GOLD = HexColor('#aa7627')
RED = HexColor('#a33b38')
PALE_RED = HexColor('#fbefed')
WHITE = colors.white

PAGE_W, PAGE_H = A4
LEFT = RIGHT = 17 * mm
TOP = 17 * mm
BOTTOM = 16 * mm
CONTENT_W = PAGE_W - LEFT - RIGHT
CONTENT_H = PAGE_H - TOP - BOTTOM


def make_crop(name, source, box):
    src = CAPTURES / source
    dst = ASSETS / f'{name}.jpg'
    with PILImage.open(src) as image:
        image = image.convert('RGB')
        x1, y1, x2, y2 = box
        x1 = max(0, min(x1, image.width - 1))
        y1 = max(0, min(y1, image.height - 1))
        x2 = max(x1 + 1, min(x2, image.width))
        y2 = max(y1 + 1, min(y2, image.height))
        image.crop((x1, y1, x2, y2)).save(dst, 'JPEG', quality=92, optimize=True)
    return dst


IMAGES = {
    'dashboard': make_crop('dashboard', 'dashboard.jpg', (45, 0, 1540, 790)),
    'agenda': make_crop('agenda', 'dashboard-future-card-centered.jpg', (58, 55, 1545, 565)),
    'leads': make_crop('leads', 'leads-list-full.jpg', (225, 0, 1710, 1080)),
    'lead_form': make_crop('lead_form', 'lead-form-full.jpg', (480, 35, 1510, 1550)),
    'lead_detail': make_crop('lead_detail', 'lead-detail.jpg', (55, 0, 1550, 900)),
    'interaction': make_crop('interaction', 'interaction-form-view.jpg', (65, 20, 1515, 565)),
    'complete': make_crop('complete', 'followup-complete-view.jpg', (420, 0, 1580, 780)),
    'lead_whatsapp': make_crop('lead_whatsapp', 'lead-whatsapp-full.jpg', (160, 20, 1450, 1835)),
    'properties': make_crop('properties', 'properties-list-full.jpg', (230, 0, 1740, 1080)),
    'property_top': make_crop('property_top', 'property-form-full.jpg', (165, 30, 1660, 1710)),
    'property_bottom': make_crop('property_bottom', 'property-form-full.jpg', (165, 1640, 1660, 3260)),
    'owners': make_crop('owners', 'owners-list-full.jpg', (55, 0, 1580, 920)),
    'owner_form': make_crop('owner_form', 'owner-form.jpg', (155, 0, 1560, 1710)),
    'owner_detail': make_crop('owner_detail', 'owner-detail-full.jpg', (220, 0, 1705, 1050)),
    'owner_whatsapp': make_crop('owner_whatsapp', 'owner-whatsapp-full.jpg', (160, 0, 1450, 1810)),
    'signboards': make_crop('signboards', 'signboards-list-full.jpg', (195, 0, 1510, 1520)),
    'signboard_form': make_crop('signboard_form', 'signboard-form-full.jpg', (200, 0, 1570, 1420)),
    'signboard_assign': make_crop('signboard_assign', 'signboard-assign.jpg', (205, 0, 1545, 1350)),
    'signboard_install': make_crop('signboard_install', 'signboard-install-action.jpg', (65, 0, 1515, 790)),
    'signboard_active': make_crop('signboard_active', 'signboard-active.jpg', (60, 120, 1525, 800)),
    'csv': make_crop('csv', 'csv-import.jpg', (650, 120, 2240, 1700)),
    'reports': make_crop('reports', 'reports.jpg', (550, 0, 2310, 1530)),
}

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='Body', fontName='Segoe', fontSize=9.3, leading=13.5,
                          textColor=INK, spaceAfter=5))
styles.add(ParagraphStyle(name='BodySmall', fontName='Segoe', fontSize=8.2, leading=11.5,
                          textColor=INK, spaceAfter=4))
styles.add(ParagraphStyle(name='Small', fontName='Segoe', fontSize=7.6, leading=10.3,
                          textColor=MUTED, spaceAfter=3))
styles.add(ParagraphStyle(name='Tiny', fontName='Segoe', fontSize=6.8, leading=8.8,
                          textColor=MUTED))
styles.add(ParagraphStyle(name='H1', fontName='Segoe-Bold', fontSize=25, leading=29,
                          textColor=NAVY, spaceAfter=6))
styles.add(ParagraphStyle(name='H2', fontName='Segoe-Bold', fontSize=17, leading=21,
                          textColor=NAVY, spaceBefore=1, spaceAfter=6, keepWithNext=True))
styles.add(ParagraphStyle(name='H3', fontName='Segoe-Bold', fontSize=10.5, leading=13.5,
                          textColor=TEAL, spaceBefore=6, spaceAfter=3, keepWithNext=True))
styles.add(ParagraphStyle(name='Lead', fontName='Segoe', fontSize=10.4, leading=14.8,
                          textColor=MUTED, spaceAfter=7))
styles.add(ParagraphStyle(name='Kicker', fontName='Segoe-Bold', fontSize=7.4, leading=9,
                          textColor=TEAL, spaceAfter=4))
styles.add(ParagraphStyle(name='Step', fontName='Segoe', fontSize=8.8, leading=12.7,
                          textColor=INK, leftIndent=20, firstLineIndent=-20, spaceAfter=4))
styles.add(ParagraphStyle(name='GuideBullet', fontName='Segoe', fontSize=8.7, leading=12.2,
                          textColor=INK, leftIndent=12, firstLineIndent=-8, spaceAfter=3))
styles.add(ParagraphStyle(name='Callout', fontName='Segoe', fontSize=8.3, leading=11.6,
                          textColor=NAVY))
styles.add(ParagraphStyle(name='Caption', fontName='Segoe-Italic', fontSize=7.1, leading=9.4,
                          textColor=MUTED, alignment=TA_CENTER, spaceBefore=3, spaceAfter=2))
styles.add(ParagraphStyle(name='TableHead', fontName='Segoe-Bold', fontSize=7.8, leading=9.5,
                          textColor=WHITE))
styles.add(ParagraphStyle(name='TableCell', fontName='Segoe', fontSize=7.8, leading=10.2,
                          textColor=INK))
styles.add(ParagraphStyle(name='CoverTitle', fontName='Segoe-Bold', fontSize=29, leading=34,
                          textColor=WHITE, spaceAfter=7))
styles.add(ParagraphStyle(name='CoverSub', fontName='Segoe', fontSize=12, leading=17,
                          textColor=HexColor('#d9e7eb')))
styles.add(ParagraphStyle(name='CoverTag', fontName='Segoe-Bold', fontSize=8, leading=10,
                          textColor=HexColor('#aee3d9'), spaceAfter=7))


def P(text, style='Body'):
    return Paragraph(text, styles[style])


def step(number, text):
    return P(f'<b>{number}.</b>  {text}', 'Step')


def bullets(items, style='GuideBullet'):
    return [P(f'-  {item}', style) for item in items]


def section(kicker, title, intro=None):
    items = [P(kicker.upper(), 'Kicker'), P(title, 'H2')]
    if intro:
        items.append(P(intro, 'Body'))
    items.append(HRFlowable(width='100%', thickness=0.7, color=LINE,
                            spaceBefore=1, spaceAfter=7))
    return items


def callout(title, body, bg=PALE, edge=TEAL):
    cell = P(f'<b>{title}</b><br/>{body}', 'Callout')
    table = Table([[cell]], colWidths=[CONTENT_W])
    table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), bg),
        ('BOX', (0, 0), (-1, -1), 0.45, LINE),
        ('LINEBEFORE', (0, 0), (0, -1), 3, edge),
        ('LEFTPADDING', (0, 0), (-1, -1), 9),
        ('RIGHTPADDING', (0, 0), (-1, -1), 9),
        ('TOPPADDING', (0, 0), (-1, -1), 7),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ]))
    return table


def fit_image(key, max_width, max_height):
    path = IMAGES[key]
    with PILImage.open(path) as im:
        w, h = im.size
    scale = min((max_width * mm) / w, (max_height * mm) / h)
    return Image(str(path), width=w * scale, height=h * scale, hAlign='CENTER')


def figure(key, max_width, max_height, caption):
    return [fit_image(key, max_width, max_height), P(caption, 'Caption')]


def image_cell(key, max_width, max_height, caption):
    return [fit_image(key, max_width, max_height), Spacer(1, 2), P(caption, 'Caption')]


def two_col(left, right, widths=None, paddings=7):
    if widths is None:
        widths = [CONTENT_W / 2, CONTENT_W / 2]
    table = Table([[left, right]], colWidths=widths, hAlign='LEFT')
    table.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), paddings),
        ('RIGHTPADDING', (0, 0), (-1, -1), paddings),
        ('TOPPADDING', (0, 0), (-1, -1), 2),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 2),
    ]))
    return table


def page_chrome(canvas, doc):
    canvas.saveState()
    if doc.page > 1:
        canvas.setStrokeColor(LINE)
        canvas.setLineWidth(0.6)
        canvas.line(LEFT, PAGE_H - 11.5 * mm, PAGE_W - RIGHT, PAGE_H - 11.5 * mm)
        canvas.setFont('Segoe-Bold', 7.1)
        canvas.setFillColor(NAVY)
        canvas.drawString(LEFT, PAGE_H - 8.9 * mm, 'PLATAFORMA PINCIARA')
        canvas.setFont('Segoe', 7.1)
        canvas.setFillColor(MUTED)
        canvas.drawRightString(PAGE_W - RIGHT, PAGE_H - 8.9 * mm, 'MANUAL DE TREINAMENTO')
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.6)
    canvas.line(LEFT, 11.5 * mm, PAGE_W - RIGHT, 11.5 * mm)
    canvas.setFont('Segoe', 7)
    canvas.setFillColor(MUTED)
    canvas.drawString(LEFT, 7.8 * mm, 'Capturas ilustrativas com dados fictícios')
    canvas.drawRightString(PAGE_W - RIGHT, 7.8 * mm, f'{doc.page:02d}')
    canvas.restoreState()


doc = BaseDocTemplate(
    str(OUT), pagesize=A4, leftMargin=LEFT, rightMargin=RIGHT,
    topMargin=TOP, bottomMargin=BOTTOM,
    title='Manual de Treinamento - Plataforma Pinciara',
    author='Plataforma Pinciara',
    subject='Guia passo a passo para a equipe da imobiliária',
)
frame = Frame(LEFT, BOTTOM, CONTENT_W, CONTENT_H, id='normal',
              leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
doc.addPageTemplates([PageTemplate(id='main', frames=[frame], onPage=page_chrome)])
story = []


# CAPA
story.append(Spacer(1, 8 * mm))
cover = Table([
    [P('GUIA DE TREINAMENTO', 'CoverTag')],
    [P('Plataforma<br/>Pinciara', 'CoverTitle')],
    [P('Manual de uso do CRM imobiliário<br/>Passo a passo para quem está começando', 'CoverSub')],
], colWidths=[CONTENT_W - 22 * mm])
cover.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, -1), NAVY),
    ('BOX', (0, 0), (-1, -1), 0, NAVY),
    ('LEFTPADDING', (0, 0), (-1, -1), 13 * mm),
    ('RIGHTPADDING', (0, 0), (-1, -1), 13 * mm),
    ('TOPPADDING', (0, 0), (-1, -1), 0),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 3 * mm),
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
]))
story.append(cover)
story += [Spacer(1, 6 * mm), P('Da agenda ao próximo passo', 'H3')]
story.append(P('Aprenda a encontrar clientes, registrar atendimentos, acompanhar imóveis e proprietários e manter cada retorno organizado. As telas deste guia mostram exemplos fictícios capturados na versão atual do sistema.', 'Body'))
story += [Spacer(1, 3 * mm)]
story += figure('dashboard', 168, 91, 'Exemplo da Dashboard: agenda e acessos principais.')
story += [Spacer(1, 2 * mm), callout('A ROTINA EM UMA LINHA', 'Consulte a agenda, abra a ficha certa, registre o que aconteceu e deixe o próximo passo combinado com data e responsável.')]
story += [Spacer(1, 4 * mm), P('EDIÇÃO DE TREINAMENTO  |  OUTUBRO DE 2026', 'Small'), PageBreak()]


# 1. PRIMEIRO ACESSO
story += section('01 | Primeiro acesso', 'Entrar e se orientar', 'O CRM funciona pelo navegador na rede local da imobiliária. O administrador informa o endereço correto e cria o acesso individual de cada pessoa.')
story += [P('Entrar no sistema', 'H3')]
story += [step(1, 'Abra o navegador no computador da imobiliária e acesse o endereço interno informado pelo administrador.'),
           step(2, 'Digite seu e-mail corporativo e sua senha. Se você ainda não tem acesso ou não consegue entrar, fale com o gestor.'),
           step(3, 'Selecione <b>Entrar na Plataforma</b>. Ao terminar o expediente, use <b>Sair</b> no canto superior da tela.')]
story += [P('Módulos do menu do corretor', 'H3')]
data = [[P('MENU', 'TableHead'), P('USO MAIS COMUM', 'TableHead')]]
for name, purpose in [
    ('Dashboard', 'Agenda de retornos e visão geral para começar o dia.'),
    ('Leads', 'Clientes e oportunidades em acompanhamento.'),
    ('Imóveis', 'Catálogo, localização e interessados relacionados.'),
    ('Proprietários', 'Dados dos proprietários e imóveis vinculados.'),
    ('Placas', 'Estoque, colocação, instalação e retirada de placas.'),
]:
    data.append([P(name, 'TableCell'), P(purpose, 'TableCell')])
t = Table(data, colWidths=[36 * mm, CONTENT_W - 36 * mm], repeatRows=1)
t.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), NAVY),
    ('GRID', (0, 0), (-1, -1), 0.4, LINE),
    ('ROWBACKGROUNDS', (0, 1), (-1, -1), [WHITE, HexColor('#f7f9fa')]),
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('LEFTPADDING', (0, 0), (-1, -1), 7),
    ('RIGHTPADDING', (0, 0), (-1, -1), 7),
    ('TOPPADDING', (0, 0), (-1, -1), 5),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
]))
story += [t, Spacer(1, 6), callout('SEU MENU PODE SER DIFERENTE', 'Relatórios, Usuários, Auditoria e Sistema são ferramentas de administrador. Se elas não aparecerem para você, o perfil de corretor está funcionando como esperado.', bg=PALE_BLUE, edge=BLUE)]
story += [Spacer(1, 5), P('Palavras usadas neste guia', 'H3')]
story += bullets([
    '<b>Lead:</b> cliente ou oportunidade que pode comprar, vender ou alugar um imóvel.',
    '<b>Follow-up:</b> retorno combinado para uma data e hora futuras.',
    '<b>Estágio:</b> etapa atual da negociação no funil.',
    '<b>Proprietário:</b> pessoa dona de um ou mais imóveis anunciados.',
])
story.append(PageBreak())


# 2. DASHBOARD
story += section('02 | Rotina do corretor', 'Comece pela Dashboard', 'A agenda ajuda a priorizar quem precisa de retorno. Os cartões resumem atrasados, contatos do dia, futuros e retornos concluídos.')
story += figure('dashboard', 170, 119, 'Dashboard demonstrativa. Os números dependem dos retornos registrados na sua equipe.')
story += [Spacer(1, 3), P('Sua primeira conferência do dia', 'H3')]
story += [step(1, 'No menu, abra <b>Dashboard</b>.'),
           step(2, 'Confira primeiro <b>Retornos Atrasados</b>, depois <b>Contatos de Hoje</b>.'),
           step(3, 'Veja os futuros para preparar os contatos seguintes.'),
           step(4, 'Abra o nome do lead para conferir o histórico antes de ligar ou enviar uma mensagem.'),
           step(5, 'Depois do contato, registre o resultado na ficha e crie o próximo retorno, se houver.')]
story += [Spacer(1, 2), callout('A AGENDA MOSTRA O PRÓXIMO COMPROMISSO', 'Cada retorno tem uma data e hora, um responsável e um objetivo. Se a conversa combinar um novo contato, deixe esse próximo passo registrado.')]
story.append(PageBreak())


# 3. FILTROS DE AGENDA
story += section('03 | Agenda', 'Filtrar e trabalhar um retorno', 'Use a faixa de filtros para localizar os retornos de hoje, os atrasados ou toda a equipe.')
story += figure('agenda', 170, 60, 'Exemplo de um retorno futuro. A tela mostra o responsável, o objetivo e as ações disponíveis.')
story += [Spacer(1, 3), P('Como localizar o que precisa', 'H3')]
story += [step(1, 'Em <b>Situação</b>, selecione Atrasados, Hoje, Futuros, Concluídos ou Todos.'),
           step(2, 'Em <b>Responsável pelo Retorno</b>, deixe seus retornos ou escolha a visão da equipe, se disponível.'),
           step(3, 'Opcionalmente filtre por estágio, data inicial/final ou nome do lead. Selecione <b>Filtrar</b>.'),
           step(4, 'Abra a ficha do cliente ou use <b>Registrar Resultado</b> para documentar o contato.'),
           step(5, 'Se não conseguiu falar, use <b>Não Atendeu (+2h)</b> quando esse novo horário fizer sentido, ou selecione <b>Reagendar</b>.')]
story += [Spacer(1, 2), callout('LIMPAR FILTROS', 'Use o botão <b>Limpar</b> para voltar à visualização padrão da agenda. A data do retorno não muda ao aplicar ou limpar filtros.')]
story.append(PageBreak())


# 4. LISTA DE LEADS
story += section('04 | Leads', 'Encontrar o cliente antes de cadastrar', 'A lista permite localizar oportunidades e conferir rapidamente estágio, origem, responsável e próximo retorno.')
story += figure('leads', 170, 132, 'Na lista de Leads, a busca e os filtros ficam acima dos registros.')
story += [Spacer(1, 2), P('Pesquisar e abrir uma ficha', 'H3')]
story += [step(1, 'Abra <b>Leads</b> no menu superior.'),
           step(2, 'Digite nome, telefone ou e-mail em <b>Buscar por nome, telefone ou e-mail</b>.'),
           step(3, 'Refine pelos estágios rápidos ou pelos filtros de estágio, responsável e origem.'),
           step(4, 'Selecione o nome ou <b>Ver Ficha</b> para abrir o atendimento completo.')]
story += [Spacer(1, 2), callout('EVITE UM CADASTRO DUPLICADO', 'Pesquise antes de criar. Se o mesmo e-mail ou telefone já estiver cadastrado, abra o lead existente; o sistema impede a criação manual de outro registro com o mesmo contato.', bg=PALE_GOLD, edge=GOLD)]
story.append(PageBreak())


# 5. CRIAR LEAD
story += section('05 | Leads', 'Cadastrar uma nova oportunidade', 'Crie uma ficha por cliente e inclua informações que ajudem a equipe a continuar o atendimento.')
left = [P('Preencha o formulário', 'H3'),
        step(1, 'Na lista de Leads, selecione <b>Novo Lead</b>.'),
        step(2, 'Informe o <b>Nome do Lead / Cliente</b>. O nome é obrigatório.'),
        step(3, 'Telefone e e-mail são opcionais, mas preencha os dados de contato que a pessoa forneceu.'),
        step(4, 'Escolha o <b>Canal de Origem</b> e confirme o <b>Corretor Responsável</b>.'),
        step(5, 'Em <b>Observações Iniciais / Perfil de Interesse</b>, anote tipo de imóvel, bairro, faixa de preço e prazo.'),
        step(6, 'Se já souber o código do imóvel, informe-o em <b>Código do imóvel de interesse</b>. O lead será vinculado a esse imóvel.'),
        step(7, 'Se combinaram um retorno, informe data e hora. Depois selecione <b>Salvar e Iniciar Atendimento</b>.')]
right = image_cell('lead_form', 82, 139, 'Formulário de novo lead. Nome obrigatório; os demais dados dependem do atendimento.')
story.append(two_col(left, right, [CONTENT_W * 0.52, CONTENT_W * 0.48], paddings=4))
story += [Spacer(1, 2), callout('CÓDIGO DE IMÓVEL', 'Confira o código antes de salvar. Se ele não corresponder a um imóvel cadastrado, a tela indicará o erro para você corrigir.')]
story.append(PageBreak())


# 6. FICHA DO LEAD
story += section('06 | Atendimento', 'Entender a ficha do lead', 'A ficha reúne estágio, retorno, dados cadastrais, responsável, histórico e imóveis relacionados.')
story += figure('lead_detail', 170, 104, 'Exemplo de ficha: pipeline, retorno programado, dados e histórico aparecem na mesma tela.')
story += [Spacer(1, 2), P('Avançar a negociação', 'H3')]
story += [step(1, 'Leia o estágio atual no <b>Pipeline de Vendas</b>.'),
           step(2, 'Quando a oportunidade avançar, escolha uma opção permitida em <b>Mudar Estágio do Lead</b> e confirme.'),
           step(3, 'As próximas etapas disponíveis dependem do estágio atual. Use os nomes apresentados na própria ficha.'),
           step(4, 'Para marcar como <b>Perdido</b>, informe um motivo específico. Para fechar como ganho, use a etapa correspondente.')]
story += [P('Imóveis relacionados', 'H3'), P('A seção <b>Imóveis vinculados</b> mostra as relações que alguém da equipe confirmou. Sugestões automáticas aparecem em uma área separada; elas só viram vínculo quando você seleciona <b>Vincular</b>.', 'BodySmall')]
story.append(PageBreak())


# 7. INTERAÇÃO E RESULTADO DO FOLLOW-UP
story += section('07 | Registrar o atendimento', 'Guardar o que aconteceu e concluir o retorno', 'Cada ligação, conversa, visita ou anotação deve ficar na ficha. Isso permite que outra pessoa entenda o histórico.')
left = [P('1. Registrar uma interação', 'H3'),
        step(1, 'Na ficha, localize <b>Histórico de Interações</b> e selecione <b>Nova Interação</b> ou <b>Registrar Novo Contato ou Nota</b>.'),
        step(2, 'Escolha o tipo de contato e confira a data e hora.'),
        step(3, 'Em <b>Observações do Contato</b>, descreva o que foi falado e o que ficou combinado.'),
        step(4, 'Selecione <b>Salvar Interação no Histórico</b>.')]
right = [P('2. Concluir um retorno', 'H3'),
         step(1, 'No retorno ativo, selecione <b>Concluir / Registrar Resultado</b>.'),
         step(2, 'Informe canal, data e hora em que o contato ocorreu.'),
         step(3, 'Preencha o resultado. Esse campo é obrigatório.'),
         step(4, 'Se combinaram nova data, abra <b>Agendar Próximo Retorno</b> e informe data, responsável e objetivo.'),
         step(5, 'Selecione <b>Salvar Resultado &amp; Concluir</b>.')]
pics = Table([[image_cell('interaction', 79, 38, 'Formulário para registrar uma ligação, visita, conversa ou nota.'),
               image_cell('complete', 79, 60, 'Janela de conclusão com campo obrigatório para o resultado.')]],
             colWidths=[CONTENT_W / 2, CONTENT_W / 2])
pics.setStyle(TableStyle([('VALIGN', (0, 0), (-1, -1), 'TOP'),
                          ('LEFTPADDING', (0, 0), (-1, -1), 5),
                          ('RIGHTPADDING', (0, 0), (-1, -1), 5),
                          ('TOPPADDING', (0, 0), (-1, -1), 3),
                          ('BOTTOMPADDING', (0, 0), (-1, -1), 1)]))
story.append(pics)
story += [Spacer(1, 2), two_col(left, right, [CONTENT_W / 2, CONTENT_W / 2], paddings=5)]
story += [Spacer(1, 2), callout('SE O CONTATO NÃO ACONTECEU', 'Registre a tentativa com clareza e programe um novo horário. Para reagendar, informe a nova data, o responsável e o motivo da mudança.')]
story.append(PageBreak())


# 8. WHATSAPP PARA LEADS
story += section('08 | WhatsApp com leads', 'Preparar, revisar e registrar a mensagem', 'O CRM prepara uma sugestão. Você confere e conclui o envio no WhatsApp Desktop.')
left = [P('Passo a passo', 'H3'),
        step(1, 'Na ficha do lead, selecione <b>WhatsApp</b>.'),
        step(2, 'Escolha a <b>Régua do contato</b>, por exemplo Primeiro contato, Pós-visita ou Follow-up.'),
        step(3, 'Confira o telefone com DDD. Se estiver vazio, informe o número.'),
        step(4, 'Revise e edite a mensagem sugerida. Confira nome, imóvel e contexto antes de abrir.'),
        step(5, 'Selecione <b>Abrir WhatsApp Desktop</b> e conclua o envio no aplicativo.'),
        step(6, 'Volte ao CRM e marque <b>Confirmado pelo corretor</b>, <b>Não enviado</b> ou <b>Cancelar contato</b>.')]
right = image_cell('lead_whatsapp', 83, 174, 'Tela de WhatsApp para um lead. A mensagem permanece editável.')
story.append(two_col(left, right, [CONTENT_W * 0.49, CONTENT_W * 0.51], paddings=4))
story += [Spacer(1, 1), callout('O CRM NÃO CONFIRMA O ENVIO SOZINHO', 'O WhatsApp abre em outra aplicação. Só marque <b>Confirmado pelo corretor</b> depois de conferir que você realmente enviou a mensagem.', bg=PALE_GOLD, edge=GOLD)]
story.append(PageBreak())


# 9. IMOVEIS
story += section('09 | Imóveis', 'Consultar o catálogo', 'Use a busca para encontrar um imóvel pelo código ou pelas informações de localização.')
story += figure('properties', 170, 125, 'A lista mostra código, localização, valor, status e quantidade de leads relacionados.')
story += [Spacer(1, 2), P('Pesquisar e abrir um imóvel', 'H3')]
story += [step(1, 'Abra <b>Imóveis</b> no menu superior.'),
           step(2, 'Digite código, endereço, bairro, cidade ou lead em <b>Busca contextual</b>.'),
           step(3, 'Refine por status, tipo ou cidade e selecione <b>Buscar imóveis</b>.'),
           step(4, 'Abra o nome do imóvel para conferir os dados e os leads relacionados.'),
           step(5, 'Para cadastrar, selecione <b>Cadastrar imóvel</b>.')]
story += [Spacer(1, 2), callout('VÍNCULOS', 'A lista mostra quantos leads estão relacionados ao imóvel. Abra o registro para conferir cada vínculo e suas observações.')]
story.append(PageBreak())


# 10. CADASTRO DO IMÓVEL - IDENTIFICAÇÃO
story += section('10 | Cadastrar imóvel', 'Preencher identificação e localização', 'O cadastro atualizado organiza os dados em blocos. Na primeira parte, complete o código, o tipo e o endereço do imóvel.')
left = [P('Identificação', 'H3'),
        step(1, 'Em <b>Código do imóvel</b>, informe o identificador usado pela imobiliária. O código deve ser único.'),
        step(2, 'Escolha o <b>Tipo de imóvel</b> e o <b>Status operacional</b>.'),
        P('Localização', 'H3'),
        step(3, 'Preencha endereço/logradouro, bairro e cidade.'),
        step(4, 'Se houver, adicione um link compartilhável do Google Maps.'),
        P('Áreas', 'H3'),
        step(5, 'Informe área construída ou área do terreno quando esses dados estiverem disponíveis.')]
right = image_cell('property_top', 82, 145, 'Parte superior do formulário de imóvel: identificação, endereço e áreas.')
story.append(two_col(left, right, [CONTENT_W * 0.48, CONTENT_W * 0.52], paddings=4))
story += [Spacer(1, 2), callout('CAMPOS ESSENCIAIS', 'Código, tipo, status operacional, endereço, bairro e cidade são os principais dados de identificação. A tela destaca os campos que precisam de correção antes de salvar.')]
story.append(PageBreak())


# 11. CADASTRO DO IMOVEL - NEGOCIACAO
story += section('11 | Cadastrar imóvel', 'Informar negociação, encargos e divulgação', 'Na segunda parte, complete o tipo de negociação e os valores que a imobiliária já confirmou.')
left = [P('Negociação', 'H3'),
        step(1, 'Escolha a <b>Intenção</b> e a <b>Pretensão</b>.'),
        step(2, 'Preencha valor de venda e/ou de locação, conforme o anúncio. Use vírgula nos centavos.'),
        P('Encargos e divulgação', 'H3'),
        step(3, 'Informe condomínio e IPTU quando conhecidos.'),
        step(4, 'Cole o link do anúncio, se já existir. Se o link ficar vazio, o sistema monta um endereço padrão usando o código.'),
        P('Antes de salvar', 'H3'),
        step(5, 'Acrescente observações internas úteis e selecione <b>Salvar imóvel</b>.')]
right = image_cell('property_bottom', 82, 145, 'Parte inferior do formulário: valores, encargos, link e observações.')
story.append(two_col(left, right, [CONTENT_W * 0.48, CONTENT_W * 0.52], paddings=4))
story += [Spacer(1, 2), callout('DADOS AINDA NÃO CONFIRMADOS', 'Deixe em branco os valores opcionais que a equipe ainda não confirmou. Não complete o cadastro com estimativas apresentadas como se fossem valores oficiais.')]
story.append(PageBreak())


# 12. PROPRIETARIOS - BUSCA
story += section('12 | Proprietários', 'Encontrar um proprietário', 'O cadastro de proprietário centraliza contatos e os imóveis que pertencem àquela pessoa.')
story += figure('owners', 170, 104, 'A lista permite pesquisar por nome, telefone, e-mail ou código de imóvel.')
story += [Spacer(1, 2), P('Pesquisar ou iniciar um cadastro', 'H3')]
story += [step(1, 'Abra <b>Proprietários</b>.'),
           step(2, 'Pesquise nome, telefone, e-mail ou código de imóvel.'),
           step(3, 'Selecione o nome ou <b>Abrir</b> para conferir a ficha.'),
           step(4, 'Se o registro não existir, selecione <b>Cadastrar proprietário</b>.')]
story += [Spacer(1, 2), callout('ANTES DE CADASTRAR', 'Pesquise o proprietário e o código do imóvel para evitar dois cadastros para a mesma pessoa ou o mesmo imóvel.')]
story.append(PageBreak())


# 13. PROPRIETARIOS - CADASTRO
story += section('13 | Proprietários', 'Cadastrar e relacionar imóveis', 'O nome é obrigatório. Telefone, e-mail, observações e vínculos ajudam a equipe a manter o contato atualizado.')
left = [P('No formulário', 'H3'),
        step(1, 'Selecione <b>Cadastrar proprietário</b> e informe o nome.'),
        step(2, 'Preencha telefone e e-mail, se disponíveis.'),
        step(3, 'Em <b>Imóveis vinculados</b>, pesquise e marque os imóveis daquela pessoa.'),
        step(4, 'Se ainda não quiser relacionar os imóveis, você pode fazer isso depois pelo código na ficha.'),
        step(5, 'Inclua observações úteis e selecione <b>Salvar proprietário</b>.')]
right = image_cell('owner_form', 84, 133, 'Cadastro de proprietário com busca e seleção de imóveis.')
story.append(two_col(left, right, [CONTENT_W * 0.48, CONTENT_W * 0.52], paddings=4))
story += [Spacer(1, 2), callout('MANTER OS VÍNCULOS EM DIA', 'Na ficha do proprietário, confira quais imóveis estão associados e remova um vínculo somente quando ele realmente não pertencer mais àquela pessoa.')]
story.append(PageBreak())


# 14. FICHA DO PROPRIETARIO
story += section('14 | Ficha do proprietário', 'Conferir os imóveis e iniciar uma atualização', 'A ficha mostra contatos, observações e cada imóvel vinculado. Use o código para incluir outro imóvel cadastrado.')
story += figure('owner_detail', 164, 116, 'Exemplo de ficha com dois imóveis relacionados e opção de vincular outro pelo código.')
story += [Spacer(1, 2), P('Conferir ou atualizar os vínculos', 'H3')]
story += [step(1, 'Abra o proprietário pela lista.'),
           step(2, 'Confira telefone, e-mail, observações e a tabela de imóveis.'),
           step(3, 'Para relacionar outro imóvel, informe o código em <b>Vincular outro imóvel pelo código</b> e selecione <b>Vincular imóvel</b>.'),
           step(4, 'Para preparar uma confirmação mensal dos dados do anúncio, selecione <b>WhatsApp</b>.')]
story.append(PageBreak())


# 15. WHATSAPP PROPRIETARIO
story += section('15 | WhatsApp com proprietário', 'Confirmar disponibilidade dos imóveis', 'O sistema prepara uma mensagem de atualização mensal usando os imóveis vinculados à ficha.')
left = [P('Conferir antes de abrir', 'H3'),
        step(1, 'Na ficha do proprietário, selecione <b>WhatsApp</b>.'),
        step(2, 'Escolha atualização mensal para um imóvel ou para dois ou mais imóveis.'),
        step(3, 'Confira telefone, códigos dos imóveis e texto sugerido.'),
        step(4, 'Revise e edite a mensagem antes de selecionar <b>Abrir WhatsApp Desktop</b>.'),
        step(5, 'Conclua no WhatsApp e volte ao CRM para indicar se o contato foi confirmado, não enviado ou cancelado.')]
right = image_cell('owner_whatsapp', 84, 176, 'Mensagem mensal do proprietário com os imóveis relacionados.')
story.append(two_col(left, right, [CONTENT_W * 0.48, CONTENT_W * 0.52], paddings=4))
story += [Spacer(1, 2), callout('CONFIRMAÇÃO MANUAL', 'O CRM não consegue ler o resultado no WhatsApp. Só marque como confirmado depois de verificar que a mensagem foi enviada.')]
story.append(PageBreak())


# 16. PLACAS - ESTOQUE
story += section('16 | Placas', 'Acompanhar o estoque no quadro', 'Cada placa passa por etapas de compra, preparação e uso. As mudanças ficam registradas no histórico.')
story += figure('signboards', 165, 164, 'Quadro de etapas. Cada coluna mostra quantas placas estão em cada etapa.')
story += [Spacer(1, 1), P('Cadastrar e mover uma placa', 'H3')]
story += [step(1, 'Abra <b>Placas</b> e selecione <b>Cadastrar placa</b>.'),
           step(2, 'Escolha um responsável ativo. Informe material, altura, comprimento e tipo.'),
           step(3, 'Use o quadro para mover a placa pelo seletor <b>Mover para</b> e botão <b>Mover</b>.'),
           step(4, 'Avance conforme o trabalho real: <b>A fazer</b>, <b>Pago</b>, <b>Pronto</b>, <b>Disponível</b>.'),
           step(5, 'Quando for usada em um imóvel, selecione <b>Colocar placa</b> e escolha o imóvel. Ela vai para <b>Usadas</b>.')]
story += [Spacer(1, 1), callout('ETAPA E SITUAÇÃO SÃO COISAS DIFERENTES', 'A etapa mostra o ciclo da placa no estoque. A situação mostra se a placa está pendente, ativa, vencida ou retirada.')]
story.append(PageBreak())


# 17. PLACAS - COLOCAR, INSTALAR E RETIRAR
story += section('17 | Placas', 'Colocar, instalar e retirar', 'Vincular a placa ao imóvel não confirma automaticamente que ela foi instalada. Registre a instalação quando o trabalho for concluído.')
top = Table([[image_cell('signboard_assign', 82, 88, '1. Escolha o imóvel no qual a placa será usada.'),
              image_cell('signboard_install', 82, 72, '2. Informe data e confirmação para registrar a instalação.')]],
            colWidths=[CONTENT_W / 2, CONTENT_W / 2])
top.setStyle(TableStyle([('VALIGN', (0, 0), (-1, -1), 'TOP'),
                         ('LEFTPADDING', (0, 0), (-1, -1), 5),
                         ('RIGHTPADDING', (0, 0), (-1, -1), 5),
                         ('TOPPADDING', (0, 0), (-1, -1), 2),
                         ('BOTTOMPADDING', (0, 0), (-1, -1), 1)]))
story.append(top)
story += [Spacer(1, 2), step(1, 'No cartão da placa disponível, selecione <b>Colocar placa</b>, pesquise o imóvel pelo código ou endereço, marque-o e confirme.'),
           step(2, 'Na ficha da placa, preencha <b>Data da instalação</b> e escreva uma confirmação objetiva, por exemplo: instalada na fachada, junto ao portão.'),
           step(3, 'Selecione <b>Confirmar instalação</b>. A situação passa para Ativa; a etapa continua Usadas.'),
           step(4, 'Ao retirar, use <b>Confirmar retirada</b>, informe a data e a confirmação. A retirada encerra o ciclo; consulte o histórico depois.')]
story += [Spacer(1, 1), callout('NÃO USE "EXCLUIR PLACA" PARA ENCERRAR O USO', 'Use a rotina de retirada para registrar o que aconteceu e preservar corretamente o histórico de instalação e retirada.', bg=PALE_GOLD, edge=GOLD)]
story.append(PageBreak())


# 18. IMPORTACAO CSV
story += section('18 | Administrador', 'Importar dados de uma planilha CSV', 'Importação é uma função de administrador. O assistente aceita leads, imóveis e proprietários e permite revisar os dados antes de gravar.')
left = [P('Etapas do assistente', 'H3'),
        step(1, 'Escolha o modelo de <b>leads</b>, <b>imóveis</b> ou <b>proprietários</b> e prepare o arquivo CSV.'),
        step(2, 'Na etapa <b>Upload do CSV</b>, selecione o tipo de dados e o arquivo correto.'),
        step(3, 'Em <b>Mapeamento &amp; Prévia</b>, associe cada coluna ao campo certo e confira as linhas mostradas.'),
        step(4, 'Em <b>Validação &amp; Impacto</b>, revise novos cadastros, duplicidades, vínculos e erros.'),
        step(5, 'Só depois de conferir, confirme a importação e leia o resumo em <b>Conclusão &amp; Lote</b>.')]
right = image_cell('csv', 83, 143, 'Primeira tela do assistente. Há modelos de planilha para os três tipos de dados.')
story.append(two_col(left, right, [CONTENT_W * 0.48, CONTENT_W * 0.52], paddings=4))
story += [Spacer(1, 2), callout('LIMITE MOSTRADO NA TELA', 'CSV de até 5 MB e 5.000 linhas por lote. A codificação aceita e o tipo escolhido aparecem no próprio assistente. Confira o impacto antes de gravar.', bg=PALE_GOLD, edge=GOLD)]
story.append(PageBreak())


# 19. ADMINISTRACAO
story += section('19 | Administrador', 'Relatórios e ferramentas da gestão', 'Essas telas aparecem somente para administradores e apoiam acompanhamento, acesso da equipe e operação do sistema.')
left = [P('Relatórios', 'H3'),
        step(1, 'Defina data inicial e final.'),
        step(2, 'Se desejar, filtre estágio e corretor responsável.'),
        step(3, 'Leia atividades do período e carteira atual por estágio.'),
        step(4, 'Escolha o conjunto de dados e selecione <b>Exportar CSV</b> para baixar um relatório.'),
        P('Outras opções de administrador', 'H3'),
        P('<b>Usuários:</b> criar, ativar ou desativar acessos e definir perfil.<br/><b>Auditoria:</b> consultar eventos por usuário, data e tipo de ação.<br/><b>Sistema:</b> conferir integridade e armazenamento e consultar ou gerar backups.', 'BodySmall')]
right = image_cell('reports', 84, 146, 'Exemplo de relatório gerencial com filtros e resumo da carteira.')
story.append(two_col(left, right, [CONTENT_W * 0.48, CONTENT_W * 0.52], paddings=4))
story += [Spacer(1, 2), callout('RESTRITO AO PERFIL ADMINISTRADOR', 'Se a imobiliária precisar importar uma planilha, criar um usuário ou consultar esses relatórios, peça ao administrador da conta para executar a tarefa.')]
story.append(PageBreak())


# 20. CHECKLIST
story += section('20 | Resumo para o dia a dia', 'Checklist de atendimento', 'Use esta sequência em cada oportunidade para que qualquer pessoa da equipe consiga continuar o atendimento.')
checklist = [
    ('1. Ver a agenda', 'Comece pelos retornos atrasados e pelos programados para hoje.'),
    ('2. Abrir a ficha certa', 'Confira nome, telefone, estágio, responsável e última interação.'),
    ('3. Fazer o contato', 'Ligue ou abra o WhatsApp. Revise a mensagem antes de enviar.'),
    ('4. Registrar o resultado', 'Anote conversa, visita, tentativa sem resposta ou outro resultado real.'),
    ('5. Atualizar a negociação', 'Avance o estágio quando a oportunidade realmente mudar.'),
    ('6. Programar o próximo passo', 'Defina data, hora, responsável e objetivo do retorno.'),
    ('7. Fechar o expediente', 'Revise atrasados e futuros para não deixar clientes sem acompanhamento.'),
]
data = []
for title, desc in checklist:
    data.append([P(f'<b>{title}</b>', 'TableCell'), P(desc, 'TableCell')])
t = Table(data, colWidths=[42 * mm, CONTENT_W - 42 * mm])
t.setStyle(TableStyle([
    ('GRID', (0, 0), (-1, -1), 0.4, LINE),
    ('ROWBACKGROUNDS', (0, 0), (-1, -1), [WHITE, HexColor('#f7f9fa')]),
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('LEFTPADDING', (0, 0), (-1, -1), 8),
    ('RIGHTPADDING', (0, 0), (-1, -1), 8),
    ('TOPPADDING', (0, 0), (-1, -1), 7),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
]))
story += [t, Spacer(1, 8), callout('QUANDO PEDIR AJUDA', 'Se uma opção não aparecer, confira se seu perfil tem acesso a ela. Se houver um erro, informe ao administrador o módulo, a tela e a mensagem apresentada. Não envie sua senha junto com o pedido de ajuda.', bg=PALE_BLUE, edge=BLUE)]
story += [Spacer(1, 10), P('Obrigado por manter o atendimento da imobiliária organizado.', 'H3'),
          P('Ao atualizar a ficha depois de cada contato, você ajuda a equipe a continuar de onde o cliente parou e reduz a chance de perder um retorno combinado.', 'Body')]


doc.build(story)
print(OUT)
