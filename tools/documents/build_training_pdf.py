from reportlab.lib import colors
from reportlab.lib.colors import HexColor
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle,
                                PageBreak, KeepTogether, HRFlowable)
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.utils import simpleSplit
from xml.sax.saxutils import escape
from pathlib import Path

ROOT = Path(r'C:\Docker\plataforma_pinciara-teste_02')
OUT = ROOT / 'output' / 'pdf' / 'manual-treinamento-plataforma-pinciara.pdf'
OUT.parent.mkdir(parents=True, exist_ok=True)
pdfmetrics.registerFont(TTFont('Segoe', r'C:\Windows\Fonts\segoeui.ttf'))
pdfmetrics.registerFont(TTFont('Segoe-Bold', r'C:\Windows\Fonts\segoeuib.ttf'))
pdfmetrics.registerFont(TTFont('Segoe-Italic', r'C:\Windows\Fonts\segoeuii.ttf'))
INK = HexColor('#172536'); NAVY = HexColor('#17324d'); TEAL = HexColor('#0f766e')
MUTED = HexColor('#526477'); LINE = HexColor('#dbe4ea'); PALE = HexColor('#eef5f6')
GOLD = HexColor('#bf8b32'); WHITE = colors.white; RED = HexColor('#a33b38')
PAGE_W, PAGE_H = A4
LEFT, RIGHT, TOP, BOTTOM = 19*mm, 19*mm, 19*mm, 17*mm

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='BodyX', fontName='Segoe', fontSize=9.4, leading=14, textColor=INK, spaceAfter=5))
styles.add(ParagraphStyle(name='SmallX', fontName='Segoe', fontSize=8, leading=11, textColor=MUTED, spaceAfter=3))
styles.add(ParagraphStyle(name='TinyX', fontName='Segoe', fontSize=7.1, leading=9.3, textColor=MUTED))
styles.add(ParagraphStyle(name='H1X', fontName='Segoe-Bold', fontSize=25, leading=30, textColor=NAVY, spaceAfter=7))
styles.add(ParagraphStyle(name='H2X', fontName='Segoe-Bold', fontSize=16, leading=20, textColor=NAVY, spaceBefore=2, spaceAfter=8, keepWithNext=True))
styles.add(ParagraphStyle(name='H3X', fontName='Segoe-Bold', fontSize=11, leading=15, textColor=TEAL, spaceBefore=8, spaceAfter=4, keepWithNext=True))
styles.add(ParagraphStyle(name='LeadX', fontName='Segoe', fontSize=11, leading=16, textColor=MUTED, spaceAfter=9))
styles.add(ParagraphStyle(name='StepX', fontName='Segoe', fontSize=9.2, leading=13.4, textColor=INK, leftIndent=25, firstLineIndent=-25, spaceAfter=5))
styles.add(ParagraphStyle(name='BulletX', fontName='Segoe', fontSize=9.1, leading=13, textColor=INK, leftIndent=12, firstLineIndent=-9, spaceAfter=3))
styles.add(ParagraphStyle(name='CallX', fontName='Segoe', fontSize=8.7, leading=12.5, textColor=NAVY))
styles.add(ParagraphStyle(name='CellX', fontName='Segoe', fontSize=8.1, leading=10.6, textColor=INK))
styles.add(ParagraphStyle(name='CellHeadX', fontName='Segoe-Bold', fontSize=8.1, leading=10, textColor=WHITE))
styles.add(ParagraphStyle(name='LabelX', fontName='Segoe-Bold', fontSize=7.5, leading=9, textColor=TEAL, tracking=0.7))

def P(text, style='BodyX'):
    return Paragraph(text, styles[style])

def step(text):
    return P(text, 'StepX')

def bullets(items):
    return [P('•  '+x, 'BulletX') for x in items]

def box(title, body, bg=PALE, edge=TEAL):
    tbl=Table([[P(f'<b>{title}</b><br/>{body}', 'CallX')]], colWidths=[PAGE_W-LEFT-RIGHT])
    tbl.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),bg),('BOX',(0,0),(-1,-1),0.5,LINE),('LINEBEFORE',(0,0),(0,-1),3,edge),('LEFTPADDING',(0,0),(-1,-1),10),('RIGHTPADDING',(0,0),(-1,-1),10),('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8)]))
    return tbl

def section(kicker, title, intro=None):
    arr=[P(kicker.upper(),'LabelX'), P(title,'H2X')]
    if intro: arr.append(P(intro,'BodyX'))
    arr.append(HRFlowable(width='100%', thickness=.7, color=LINE, spaceBefore=1, spaceAfter=9))
    return arr

def page_chrome(canvas, doc):
    canvas.saveState()
    if doc.page>1:
        canvas.setStrokeColor(LINE); canvas.setLineWidth(.6)
        canvas.line(LEFT, PAGE_H-12*mm, PAGE_W-RIGHT, PAGE_H-12*mm)
        canvas.setFont('Segoe-Bold',7.3); canvas.setFillColor(NAVY)
        canvas.drawString(LEFT, PAGE_H-9.3*mm, 'PLATAFORMA PINCIARA')
        canvas.setFont('Segoe',7.3); canvas.setFillColor(MUTED)
        canvas.drawRightString(PAGE_W-RIGHT, PAGE_H-9.3*mm, 'GUIA DE TREINAMENTO • CRM IMOBILIÁRIO')
    canvas.setStrokeColor(LINE); canvas.setLineWidth(.6)
    canvas.line(LEFT, 12*mm, PAGE_W-RIGHT, 12*mm)
    canvas.setFont('Segoe',7.3); canvas.setFillColor(MUTED)
    canvas.drawString(LEFT,8.2*mm,'Material interno de capacitação • Consulte as informações da tela para conferir os registros.')
    canvas.drawRightString(PAGE_W-RIGHT,8.2*mm,f'{doc.page:02d}')
    canvas.restoreState()

doc=BaseDocTemplate(str(OUT),pagesize=A4,leftMargin=LEFT,rightMargin=RIGHT,topMargin=TOP,bottomMargin=BOTTOM,title='Manual de Treinamento - Plataforma Pinciara',author='Plataforma Pinciara')
frame=Frame(LEFT,BOTTOM,PAGE_W-LEFT-RIGHT,PAGE_H-TOP-BOTTOM,id='normal',leftPadding=0,rightPadding=0,topPadding=0,bottomPadding=0)
doc.addPageTemplates([PageTemplate(id='main',frames=[frame],onPage=page_chrome)])
story=[]

# Capa
story += [Spacer(1,21*mm), P('MANUAL DE TREINAMENTO','LabelX'), Spacer(1,7*mm), P('Plataforma Pinciara','H1X'),
          P('CRM imobiliário • guia prático para a equipe','LeadX'), Spacer(1,5*mm)]
cover=Table([[P('<b>Da primeira consulta ao retorno agendado.</b><br/>Um passo a passo para registrar oportunidades, organizar contatos e manter o histórico comercial da imobiliária em dia.','CallX')]],colWidths=[PAGE_W-LEFT-RIGHT],rowHeights=[31*mm])
cover.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),NAVY),('TEXTCOLOR',(0,0),(-1,-1),WHITE),('BOX',(0,0),(-1,-1),0,NAVY),('LEFTPADDING',(0,0),(-1,-1),17),('RIGHTPADDING',(0,0),(-1,-1),17),('TOPPADDING',(0,0),(-1,-1),13),('BOTTOMPADDING',(0,0),(-1,-1),13)]))
cover._cellvalues[0][0]=Paragraph('<font color="white"><b>Da primeira consulta ao retorno agendado.</b><br/>Um passo a passo para registrar oportunidades, organizar contatos e manter o histórico comercial da imobiliária em dia.</font>',styles['CallX'])
story += [cover, Spacer(1,10*mm), P('O que você vai aprender','H3X')]
for item in ['Acessar o painel e organizar a agenda de retornos.','Cadastrar, localizar e acompanhar leads no funil.','Registrar ligações, mensagens, visitas e próximos passos.','Consultar imóveis e controlar placas instaladas.','Entender as rotinas adicionais disponíveis para administradores.']:
    story.append(P('•  '+item,'BulletX'))
story += [Spacer(1,7*mm), box('UM PRINCÍPIO PARA TODA A EQUIPE','Depois de cada contato com um cliente, atualize a ficha do lead: anote o que aconteceu e deixe registrado quando e quem fará o próximo retorno.'), Spacer(1,12*mm), P('PLATAFORMA PINCIARA  /  EDIÇÃO DE TREINAMENTO','TinyX'),PageBreak()]

# 1 orientação
story += section('01 • Comece por aqui','Acesso e orientação na tela','O endereço de acesso é definido pela imobiliária e pode variar conforme a instalação na rede local.')
story += [P('Entrar no sistema','H3X'), step('<b>1.</b> Abra o navegador no computador conectado à rede da imobiliária e acesse o endereço informado pelo administrador.'), step('<b>2.</b> Na tela de entrada, informe seu e-mail e sua senha de acesso.'), step('<b>3.</b> Selecione <b>Entrar</b>. Ao terminar o trabalho, use <b>Sair</b> no topo da tela.')]
story += [P('Conheça a navegação','H3X'), P('A barra superior dá acesso aos módulos principais. O menu e as opções podem variar de acordo com seu perfil de acesso.','BodyX')]
data=[[P('MENU','CellHeadX'),P('PARA QUE SERVE','CellHeadX')]]
for a,b in [('Dashboard','Agenda operacional e visão do dia.'),('Leads','Cadastro, busca e acompanhamento de clientes e oportunidades.'),('Imóveis','Catálogo de imóveis e leads relacionados.'),('Placas','Cadastro, instalação, validade e retirada de placas.')]: data.append([P(a,'CellX'),P(b,'CellX')])
t=Table(data,colWidths=[39*mm,PAGE_W-LEFT-RIGHT-39*mm],repeatRows=1,hAlign='LEFT')
t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),NAVY),('GRID',(0,0),(-1,-1),.45,LINE),('ROWBACKGROUNDS',(0,1),(-1,-1),[WHITE,HexColor('#f7f9fa')]),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),8),('RIGHTPADDING',(0,0),(-1,-1),8),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6)]))
story += [t,Spacer(1,6),box('PERFIL DE ACESSO','Todos os corretores podem consultar e atualizar os leads compartilhados. Importação CSV, relatórios gerenciais, gestão de usuários, auditoria e monitoramento do sistema são opções exclusivas do perfil administrador.')]

# 2 dashboard
story += section('02 • Rotina do corretor','Comece o dia pela Dashboard','A agenda separa retornos atrasados, previstos para hoje e futuros. Use-a para decidir quem precisa ser contatado primeiro.')
story += [P('Organizar os retornos do dia','H3X'),step('<b>1.</b> No menu superior, abra <b>Dashboard</b>.'),step('<b>2.</b> Consulte a agenda operacional e comece pelos retornos <b>atrasados</b>.'),step('<b>3.</b> Em seguida, confira os retornos previstos para <b>hoje</b> e organize os próximos horários.'),step('<b>4.</b> Abra o lead correspondente para consultar dados, últimas conversas e objetivo do contato.'),step('<b>5.</b> Após falar com a pessoa, registre o resultado no histórico e reagende ou crie um novo retorno.')]
story += [P('Atalho para uma rotina consistente','H3X'),P('<b>Início do expediente:</b> revisar atrasados e agenda de hoje. <b>Após cada contato:</b> atualizar o histórico. <b>Antes de encerrar:</b> conferir se os próximos retornos estão agendados e atribuídos à pessoa responsável.','BodyX'),box('LEMBRETE','Um retorno só fica organizado quando tem data e hora, responsável e um objetivo claro, como “enviar opções de apartamentos em Icaraí”.')]
story += [PageBreak()]

# 3 leads
story += section('03 • Leads','Cadastrar e encontrar clientes','A ficha do lead concentra os dados de contato, o estágio da oportunidade, o responsável, os retornos e o histórico de atendimento.')
story += [P('Cadastrar um novo lead','H3X'),step('<b>1.</b> Abra <b>Leads</b> e selecione <b>Novo Lead</b>.'),step('<b>2.</b> Informe o nome do cliente e preencha pelo menos um contato: telefone ou e-mail.'),step('<b>3.</b> Escolha o <b>Canal de Origem</b>, como o cliente chegou até a imobiliária.'),step('<b>4.</b> Confirme o <b>Corretor Responsável</b>. O nome exibido indica quem acompanhará a oportunidade.'),step('<b>5.</b> Registre nas observações as preferências importantes: tipo de imóvel, bairros, faixa de preço e prazo.'),step('<b>6.</b> Se já combinaram um retorno, informe data e horário no campo <b>Agendar Primeiro Retorno</b>.'),step('<b>7.</b> Selecione <b>Salvar e Iniciar Atendimento</b>.')]
story += [P('Evitar registros duplicados','H3X'),P('Antes de cadastrar, pesquise o nome, telefone ou e-mail na lista de Leads. Se o telefone ou e-mail já existir, o sistema pode atualizar o contato existente e registrar o novo contato no histórico. Confirme que abriu a ficha da pessoa certa antes de seguir.','BodyX'),P('Buscar e filtrar leads','H3X'),step('<b>1.</b> Em <b>Leads</b>, digite nome, telefone ou e-mail no campo de busca.'),step('<b>2.</b> Refine a lista pelos filtros de <b>estágio</b>, <b>responsável</b> ou <b>origem</b>.'),step('<b>3.</b> Abra o nome do lead para ver a ficha completa. Use os atalhos de estágio para consultar grupos de oportunidades.'),PageBreak()]

# 4 ficha
story += section('04 • Atendimento','Atualizar a ficha depois de cada contato','O histórico compartilhado ajuda a equipe a entender o que já foi conversado e qual deve ser o próximo passo.')
story += [P('Registrar ligação, WhatsApp, visita ou anotação','H3X'),step('<b>1.</b> Abra a ficha do lead e localize <b>Histórico de Interações</b>.'),step('<b>2.</b> Selecione <b>Nova Interação</b> ou <b>Registrar Novo Contato ou Nota</b>.'),step('<b>3.</b> Escolha o tipo de contato: ligação, WhatsApp, visita, reunião, e-mail, anotação ou outro.'),step('<b>4.</b> Confira a data e o horário do contato.'),step('<b>5.</b> Em observações, resuma o que foi conversado, o interesse demonstrado, as dúvidas e o combinado. O texto é obrigatório.'),step('<b>6.</b> Selecione <b>Salvar Interação no Histórico</b>. O sistema registra também o usuário que fez o registro.')]
story += [P('Avançar a oportunidade no funil','H3X'),P('A ficha mostra o estágio atual e as próximas transições permitidas. Quando houver uma mudança real na negociação, escolha o próximo estágio autorizado e confirme a transição. Os nomes disponíveis aparecem na própria tela.','BodyX')]
data=[[P('ESTÁGIO','CellHeadX'),P('QUANDO USAR','CellHeadX')]]
for a,b in [('Novo','Oportunidade recém-cadastrada.'),('Contato realizado','Já houve a primeira tentativa ou conversa.'),('Qualificação','Necessidades e condições estão sendo entendidas.'),('Visita agendada','Uma visita foi combinada.'),('Proposta','Há uma proposta em andamento.'),('Negociação','As condições estão sendo negociadas.'),('Ganho','Negócio concluído.'),('Perdido','Oportunidade não avançou; é obrigatório detalhar o motivo.')]:data.append([P(a,'CellX'),P(b,'CellX')])
t=Table(data,colWidths=[42*mm,PAGE_W-LEFT-RIGHT-42*mm],repeatRows=1)
t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),NAVY),('GRID',(0,0),(-1,-1),.4,LINE),('ROWBACKGROUNDS',(0,1),(-1,-1),[WHITE,HexColor('#f7f9fa')]),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),7),('RIGHTPADDING',(0,0),(-1,-1),7),('TOPPADDING',(0,0),(-1,-1),4),('BOTTOMPADDING',(0,0),(-1,-1),4)]))
story += [t,Spacer(1,6),box('ATENÇÃO AO MARCAR COMO PERDIDO','Registre um motivo claro e detalhado, por exemplo: “cliente fechou imóvel em outra região”. Essa informação fica no histórico da oportunidade.',bg=HexColor('#faf0ef'),edge=RED),PageBreak()]

# 5 followups
story += section('05 • Follow-ups','Agendar, concluir ou reagendar um retorno','A agenda da Dashboard é alimentada pelos follow-ups vinculados aos leads.')
story += [P('Criar um novo retorno','H3X'),step('<b>1.</b> Na ficha do lead, localize o bloco de follow-up e selecione <b>Agendar Follow-up</b>.'),step('<b>2.</b> Informe a data e o horário combinados.'),step('<b>3.</b> Escolha o corretor responsável pelo contato.'),step('<b>4.</b> Descreva o objetivo do retorno com uma ação específica.'),step('<b>5.</b> Salve o agendamento e confirme que ele aparece no bloco de follow-up do lead.')]
story += [P('Concluir um retorno depois do contato','H3X'),step('<b>1.</b> Abra o follow-up ativo do lead e selecione a ação para concluí-lo.'),step('<b>2.</b> Registre o resultado do atendimento e escolha o tipo de contato.'),step('<b>3.</b> Se for necessário continuar o acompanhamento, informe o próximo retorno e seu objetivo.'),step('<b>4.</b> Salve o resultado para manter juntos o histórico e o próximo passo.')]
story += [P('Reagendar um retorno','H3X'),step('<b>1.</b> No follow-up ativo, selecione <b>Reagendar Retorno</b>.'),step('<b>2.</b> Informe a nova data e horário, o responsável e o motivo da mudança.'),step('<b>3.</b> Confirme o reagendamento. O motivo também ajuda a equipe a entender a alteração.')]
story += [box('BOA PRÁTICA','Registre o contato antes de concluir a tarefa. Se não conseguiu falar com o cliente, anote a tentativa e defina quando tentar novamente.'),PageBreak()]

# 6 properties
story += section('06 • Imóveis e placas','Consultar o catálogo e relacionar interessados','Esses módulos ajudam a equipe a manter o inventário organizado e a acompanhar os leads associados a cada oportunidade.')
story += [P('Cadastrar um imóvel','H3X'),step('<b>1.</b> Abra <b>Imóveis</b> e selecione <b>Cadastrar imóvel</b>.'),step('<b>2.</b> Informe o código e o título usados pela imobiliária.'),step('<b>3.</b> Selecione o tipo e a situação do imóvel.'),step('<b>4.</b> Inclua preço quando disponível, endereço, bairro, cidade e características.'),step('<b>5.</b> Acrescente observações úteis para consulta interna e salve.'),P('Consultar e relacionar leads','H3X'),step('<b>1.</b> Pesquise imóveis por código, endereço, bairro, cidade ou lead e refine por status, tipo ou cidade.'),step('<b>2.</b> Abra o imóvel para conferir os detalhes e os leads relacionados.'),step('<b>3.</b> Para vincular interessados, localize o lead, selecione os registros correspondentes, escolha o tipo de relação e inclua uma observação quando ajudar o atendimento.'),P('Cadastrar e movimentar uma placa','H3X'),step('<b>1.</b> Abra <b>Placas</b> e selecione o cadastro de uma placa.'),step('<b>2.</b> Associe a um imóvel, informe o ponto exato de instalação e selecione uma pessoa responsável ativa.'),step('<b>3.</b> Informe uma data de validade se necessário; esse campo é opcional.'),step('<b>4.</b> Para instalar ou retirar, abra a placa, informe a data da movimentação e preencha a confirmação textual obrigatória.'),step('<b>5.</b> Consulte o histórico da ficha para verificar quem registrou cada movimentação e quando.')]
story += [box('STATUS DAS PLACAS','Pendente: ainda não instalada. Ativa: instalada e dentro da validade. Vencida: a validade passou. Retirada: a retirada foi confirmada. O histórico continua disponível após a retirada.'),PageBreak()]

# 7 admin
story += section('07 • Administração','Importação e ferramentas do administrador','Esta seção é destinada a quem possui perfil de administrador.')
story += [P('Importar leads de um arquivo CSV','H3X'),step('<b>1.</b> Em <b>Leads</b>, selecione <b>Importar CSV</b>. Se necessário, baixe o modelo disponível nessa tela.'),step('<b>2.</b> Escolha o arquivo .csv. Confira o limite de tamanho e de linhas informado na tela.'),step('<b>3.</b> Na prévia, associe as colunas do arquivo aos campos do CRM. <b>Nome é obrigatório.</b> Você pode mapear telefone, e-mail, origem, observações e corretor.'),step('<b>4.</b> Confira as primeiras linhas exibidas para confirmar que o separador e as colunas foram interpretados corretamente.'),step('<b>5.</b> Avance para a validação. Antes de gravar, revise os totais de leads novos, duplicidades e linhas com erro.'),step('<b>6.</b> Para duplicidades, escolha entre mesclar os dados e registrar o contato no histórico, ou ignorar os registros duplicados.'),step('<b>7.</b> Corrija o mapeamento quando necessário e confirme a importação somente após revisar o impacto.'),step('<b>8.</b> Ao concluir, consulte o resultado e o histórico recente de lotes importados.')]
story += [box('IMPORTAR COM CONFERÊNCIA','A etapa de validação informa que ainda não há gravação. Aproveite para revisar o resumo, as duplicidades e as linhas recusadas antes de confirmar. Se o mesmo arquivo já tiver sido usado, avalie o aviso de reprocessamento.'),P('Relatórios gerenciais','H3X'),step('<b>1.</b> Abra <b>Relatórios</b> e defina as datas inicial e final.'),step('<b>2.</b> Se desejar, filtre pelo estágio atual e pelo corretor responsável.'),step('<b>3.</b> Selecione <b>Aplicar filtros</b> e consulte a atividade e a carteira. Os detalhes disponíveis permitem abrir as fichas de origem.'),step('<b>4.</b> Para exportar, escolha leads criados, interações ou carteira atual e selecione <b>Exportar CSV</b>.')]
# 8 admin & wrapup
story += section('08 • Administração','Usuários, auditoria e sistema','Ferramentas administrativas apoiam o controle de acesso, a conferência de movimentações e a continuidade da operação.')
story += [P('Gerenciar usuários','H3X'),step('<b>1.</b> Abra <b>Usuários</b>.'),step('<b>2.</b> Para cadastrar alguém, preencha nome, e-mail, senha temporária e perfil. Senhas devem ter pelo menos 8 caracteres.'),step('<b>3.</b> Selecione <b>Cadastrar Usuário</b>. Combine a troca ou a guarda segura da senha com a pessoa.'),step('<b>4.</b> Use o seletor de papel para definir corretor ou administrador. Use <b>Desativar</b> quando for necessário remover o acesso de outra pessoa.'),P('Consultar a auditoria e o estado do sistema','H3X'),step('<b>1.</b> Em <b>Auditoria</b>, localize eventos pelo período e pelos filtros disponíveis. Abra <b>Ver Alterações</b> quando quiser comparar os dados anteriores e posteriores.'),step('<b>2.</b> Em <b>Sistema</b>, confira integridade do banco, armazenamento e estado do último backup.'),step('<b>3.</b> Use <b>Gerar Backup Agora</b> quando for necessário registrar um backup e confira a atualização na lista de backups.')]
story += [box('ACESSO ADMINISTRATIVO','A auditoria mostra ações registradas por usuário e data. Restrinja acesso de administrador às pessoas que precisam executar essas rotinas.'),P('Checklist rápido do atendimento','H3X')]
for item in ['Consulte a agenda da Dashboard.','Abra a ficha correta e releia o último histórico.','Registre o resultado real do contato.','Atualize o estágio quando a oportunidade avançar.','Deixe o próximo passo com data, hora, responsável e objetivo.','Antes de encerrar o dia, revise os retornos atrasados e futuros.']:
    story.append(P('□  '+item,'BulletX'))
story += [Spacer(1,6),P('Dúvidas e exceções','H3X'),P('Se uma opção não aparecer, confira seu perfil de acesso. Se houver dificuldade para entrar, encontrar um cliente ou concluir uma movimentação, procure o administrador da imobiliária e informe qual tela estava aberta e qual mensagem apareceu.','BodyX'),Spacer(1,8),HRFlowable(width='100%',thickness=.8,color=LINE,spaceBefore=4,spaceAfter=7),P('Este guia descreve os fluxos disponíveis na Plataforma Pinciara. O endereço, as contas e as rotinas internas de suporte são definidos pela própria imobiliária.','SmallX')]

doc.build(story)
print(OUT)
