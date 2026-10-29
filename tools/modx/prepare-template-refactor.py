"""One-time, lossless extraction from the current CMS export. Does not import static bodies."""
import json, re, html
from pathlib import Path
from html.parser import HTMLParser

root = Path(__file__).resolve().parents[2]
data = json.loads((root / '.tmp-refactor-input.json').read_text(encoding='utf-8'))
templates = {t['id']: dict(t) for t in data['templates'] if t['id'] != 1}
resources = {r['id']: r for r in data['resources']}
owners = {tid: [r['id'] for r in data['resources'] if r['template'] == tid] for tid in templates}
chunks, tvs, values, contents = {}, {}, {}, {}

def field(name, caption, group='Содержимое страницы', kind='textarea', description=''):
    tvs[name] = dict(name=name, caption=caption, category=group, type=kind, description=description)
    return '[[*' + name + ']]'

def seed(tid, name, value):
    for rid in owners[tid]: values.setdefault(str(rid), {})[name] = value

def replace_inner(tid, pattern, name, caption, group='Содержимое страницы', kind='textarea'):
    s = templates[tid]['content']; m = re.search(pattern, s, re.S)
    if not m: raise RuntimeError(f'{tid}: missing {name}')
    seed(tid, name, m.group(2)); tag = field(name, caption, group, kind)
    templates[tid]['content'] = s[:m.start(2)] + tag + s[m.end(2):]

def element(s, tag, cls):
    start = re.search(r'<'+tag+r'\b[^>]*class="[^"]*\b'+re.escape(cls)+r'\b[^\"]*"[^>]*>', s)
    if not start: raise RuntimeError('Element missing: '+cls)
    depth = 0
    for token in re.finditer(r'</?'+tag+r'\b[^>]*>', s[start.start():]):
        closing = token.group().startswith('</'); depth += -1 if closing else 1
        if depth == 0:
            end = start.start()+token.end(); inner_end = start.start()+token.start()
            return start.start(), end, start.end(), inner_end
    raise RuntimeError('Unclosed '+cls)

shared = [
 ('Шапка сайта', r'<div class="header-shell">.*?</header></div>'),
 ('Подвал сайта', r'<footer\b.*?</footer>'),
 ('Мобильное меню', r'<dialog class="mobile-menu".*?</dialog>'),
 ('Иконки интерфейса', r'<svg class="svg-sprite".*?</svg>'),
]
for name, pattern in shared:
    matches = [re.search(pattern, t['content'], re.S).group() for t in templates.values()]
    assert len(set(matches)) == 1, name+' differs'
    chunks[name] = matches[0]
    for t in templates.values(): t['content'] = re.sub(pattern, '[[$'+name+']]', t['content'], flags=re.S)

for tid, t in templates.items():
    s = t['content']; m = re.search(r'(<h1\b[^>]*>)(.*?)(</h1>)', s, re.S)
    assert m, tid
    seed(tid, 'page_heading', m.group(2)); heading = field('page_heading', 'Заголовок H1', description='Можно использовать span для красного акцента. Это заголовок страницы, SEO Title редактируется отдельно.')
    # Intro paragraph immediately after H1 (home, trainer and inner pages all use this structure).
    after = s[m.end():]; lead = re.match(r'(\s*<p\b[^>]*>)(.*?)(</p>)', after, re.S)
    if lead:
        seed(tid, 'page_intro', lead.group(2)); intro = field('page_intro', 'Описание под заголовком')
        s = s[:m.end()] + lead.group(1)+intro+lead.group(3)+after[lead.end():]
    s = s[:m.start(2)] + heading + s[m.end(2):]
    s = re.sub(r'<nav class="breadcrumbs".*?</nav>', '[[$Хлебные крошки]]', s, flags=re.S)
    cta = re.search(r'<section class="section coaches-visit".*?</section>', s, re.S)
    if cta:
        text = re.search(r'<h2[^>]*>.*?</h2><p>(.*?)</p>', cta.group(), re.S)
        assert text
        seed(tid, 'cta_description', text.group(1)); field('cta_description', 'Текст приглашения на тренировку')
        if 'Запись на тренировку' not in chunks:
            chunks['Запись на тренировку'] = cta.group().replace(text.group(1), '[[*cta_description]]', 1)
        s = s[:cta.start()] + '[[$Запись на тренировку]]' + s[cta.end():]
    t['content'] = s
chunks['Хлебные крошки'] = '<nav class="breadcrumbs" aria-label="Хлебные крошки"><ol>[[Breadcrumbs]]</ol></nav>'

# Blog: the actual article goes into the native resource content field, not a giant HTML TV.
for tid in [25,26,27]:
    group='Статья'; s=templates[tid]['content']
    replace_inner(tid, r'(<p class="article-category">)(.*?)(</p>)', 'article_category', 'Категория статьи', group, 'text')
    s=templates[tid]['content']; a,b,c,d=element(s,'figure','blog-article__photo'); photo=s[c:d]
    for attr,name,caption in [('src','article_image','Изображение статьи'),('alt','article_image_alt','Описание изображения')]:
        m=re.search(attr+r'="([^"]*)"',photo); seed(tid,name,html.unescape(m.group(1))); field(name,caption,group,'image' if attr=='src' else 'text'); photo=photo[:m.start(1)]+'[[*'+name+':htmlent]]'+photo[m.end(1):]
    s=s[:c]+photo+s[d:]; a,b,c,d=element(s,'aside','blog-toc'); s=s[:c]+'<h2>В материале</h2><ol>[[ContentContents]]</ol>'+s[d:]
    a,b,c,d=element(s,'div','blog-article__text'); body=s[c:d]; back=re.search(r'<a class="blog-back".*?</a>',body,re.S)
    assert back
    for rid in owners[tid]: contents[str(rid)] = body[:back.start()]
    s=s[:c]+'[[*content]]'+back.group()+s[d:]
    a,b,c,d=element(s,'section','blog-related'); s=s[:a]+'[[$Похожие статьи]]'+s[b:]
    templates[tid]['content']=s
assert len({templates[i]['content'] for i in [25,26,27]})==1, 'Article skeletons differ'
chunks['Похожие статьи']='<section class="section blog-related" aria-labelledby="related-title"><div class="container"><h2 id="related-title">Читайте <span>также</span></h2><div class="blog-grid">[[RelatedArticles]]</div></div></section>'
chunks['Карточка статьи']='<a class="blog-card" href="[[+url:htmlent]]"><div class="blog-card__image"><img src="[[+image:htmlent]]" width="960" height="1280" alt="Зал ZUBR GYM в Гродно" loading="lazy"></div><div class="blog-card__copy"><span class="blog-card__tag">[[+category:htmlent]]</span><h2>[[+title]]</h2><p>[[+intro]]</p><span class="blog-card__action">Читать материал <svg aria-hidden="true"><use href="#arrow"/></svg></span></div></a>'

# Legal pages share their layout; the full editable legal text is native content.
for tid in [8,9]:
    s=templates[tid]['content']; a,b,c,d=element(s,'aside','legal-toc'); s=s[:c]+'<h2>Содержание</h2><ol>[[ContentContents]]</ol>'+s[d:]
    a,b,c,d=element(s,'article','legal-content')
    for rid in owners[tid]: contents[str(rid)]=s[c:d]
    templates[tid]['content']=s[:c]+'[[*content]]'+s[d:]
assert templates[8]['content']==templates[9]['content'], 'Legal skeletons differ'

# Same-layout direction/audience pages: move varying text and image values into resource TVs.
# Keep exact markup, IDs and classes. TV values are deduplicated when the same image occurs twice.
class Tokens(HTMLParser):
    def __init__(self,s):
        super().__init__(convert_charrefs=False); self.s=s; self.lines=[0];self.events=[];self.stack=[]
        for m in re.finditer('\n',s):self.lines.append(m.end())
        self.feed(s)
    def source_offset(self):
        line,col=self.getpos();return self.lines[line-1]+col
    def handle_starttag(self,tag,attrs):
        raw=self.get_starttag_text();start=self.source_offset();self.events.append(('tag',tag,attrs,start,start+len(raw),tuple(self.stack)))
        if tag not in ['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr']:self.stack.append(tag)
    def handle_startendtag(self,tag,attrs):
        raw=self.get_starttag_text();start=self.source_offset();self.events.append(('tag',tag,attrs,start,start+len(raw),tuple(self.stack)))
    def handle_endtag(self,tag):
        self.events.append(('end',tag,[],self.source_offset(),self.source_offset()+len('</'+tag+'>'),tuple(self.stack)))
        if tag in self.stack:
            while self.stack:
                if self.stack.pop()==tag:break
    def handle_data(self,value):
        if value.strip():self.events.append(('text',self.stack[-1] if self.stack else '',value,self.source_offset(),self.source_offset()+len(value),tuple(self.stack)))
    def handle_entityref(self,name):self.events.append(('entity',name,[],self.source_offset(),self.source_offset()+len(name)+2,tuple(self.stack)))

def family(ids,prefix,category):
    parsed=[Tokens(templates[i]['content']) for i in ids]; base=templates[ids[0]]['content']; edits=[]; saved={};n=0
    assert len({len(p.events) for p in parsed})==1, category+' token count'
    def tv(column,caption,kind):
        nonlocal n
        key=tuple(column)
        if key in saved:return saved[key]
        n+=1;name=prefix+'_field_'+str(n).zfill(2); saved[key]=name;field(name,caption,category,kind)
        for tid,value in zip(ids,column):seed(tid,name,value)
        return name
    for index,event in enumerate(parsed[0].events):
        peers=[p.events[index] for p in parsed]; assert all(e[:2]==event[:2] for e in peers), category+' structure'
        typ,tag,attrs,start,end,stack=event
        if typ=='text':
            column=[e[2] for e in peers]
            if len(set(column))<2:continue
            label=('Заголовок' if tag in ['h2','h3','h4','strong'] else 'Текст')+' — '+re.sub(r'<[^>]*>','',column[0]).strip()[:65]
            name=tv(column,label,'textarea' if max(map(len,column))>90 else 'text');edits.append((start,end,'[[*'+name+']]'))
        elif typ=='tag':
            dictionaries=[dict(e[2]) for e in peers]
            assert all(list(d)==list(dictionaries[0]) for d in dictionaries), category+' attrs'
            raw=base[start:end]
            for attr,value in dictionaries[0].items():
                column=[d[attr] for d in dictionaries]
                if len(set(column))<2:continue
                assert attr in ['src','href','alt','title','aria-label','class'], (category,tag,attr,column)
                kind='hidden' if attr=='class' else 'image' if attr=='src' or (tag=='link' and attr=='href' and any(str(v).endswith(('.webp','.png','.jpg')) for v in column)) else 'text'
                label=('Изображение' if kind=='image' else {'alt':'Описание изображения','aria-label':'Подпись для доступности','href':'Ссылка','title':'Подпись'}.get(attr,'Поле'))+' — '+str(column[0])[-60:]
                name=tv(column,label,kind);m=re.search(r'\b'+attr+r'="([^"]*)"',raw);assert m
                edits.append((start+m.start(1),start+m.end(1),'[[*'+name+':htmlent]]'))
    for start,end,value in sorted(edits,reverse=True):base=base[:start]+value+base[end:]
    for tid in ids:templates[tid]['content']=base
    print(category,n,'fields')
family([14,15],'direction','Направление')
family([16,17,18,19],'audience','Аудитория')

# Trainer-specific data is separated from the reusable single-trainer layout.
tid=13;group='Тренер'
for cls,name,caption in [('coach-profile__role','coach_role','Специализация'),('coach-profile__description','coach_description','О тренере'),('coach-details__summary','coach_qualification_intro','Введение к квалификации')]:
    replace_inner(tid,r'(<p class="'+cls+r'">)(.*?)(</p>)',name,caption,group)
s=templates[tid]['content'];a,b,c,d=element(s,'div','coach-profile__photo');photo=s[c:d]
for attr,name,caption in [('src','coach_photo','Фото тренера'),('alt','coach_photo_alt','Описание фото')]:
    m=re.search(attr+r'="([^"]*)"',photo);seed(tid,name,m.group(1));field(name,caption,group,'image' if attr=='src' else 'text');photo=photo[:m.start(1)]+'[[*'+name+':htmlent]]'+photo[m.end(1):]
s=s[:c]+photo+s[d:];m=re.search(r'(<div class="coach-profile__experience"><strong>)(.*?)(</strong>)',s,re.S);assert m;seed(tid,'coach_experience',m.group(2));field('coach_experience','Стаж — число / краткая подпись',group,'text');s=s[:m.start(2)]+'[[*coach_experience:htmlent]]'+s[m.end(2):]
a,b,c,d=element(s,'dl','coach-qualifications');rows=[dict(label=html.unescape(label),value=html.unescape(value)) for label,value in re.findall(r'<dt>(.*?)</dt><dd>(.*?)</dd>',s[c:d],re.S)];assert rows
seed(tid,'coach_qualifications',json.dumps(rows,ensure_ascii=False));field('coach_qualifications','Квалификация и достижения',group,'migx')
s=s[:c]+'[[RepeatItems? &tv=`coach_qualifications` &tpl=`Квалификация тренера`]]'+s[d:]
# The remaining trainer sections become native content, keeping the first-screen layout reusable.
begin=s.index('<section class="section coach-details"');end=s.index('[[$Запись на тренировку]]',begin)
for rid in owners[tid]:contents[str(rid)]=s[begin:end]
s=s[:begin]+'[[*content]]'+s[end:];templates[tid]['content']=s
chunks['Квалификация тренера']='<div><dt>[[+label:htmlent]]</dt><dd>[[+value:htmlent]]</dd></div>'

names={2:'Главная',3:'Ошибка 404',4:'Зал — обзор',5:'Направления — список',6:'Для кого — обзор',7:'Информация — обзор',8:'Документ',10:'Тренеры — список',11:'Первый визит',12:'Галерея',13:'Тренер — одиночная',14:'Направление',16:'Аудитория',20:'Блог — список',21:'Контакты',22:'Вопросы и ответы',23:'Цены',24:'Расписание',25:'Статья блога',28:'Технические работы'}
merges={9:8,15:14,17:16,18:16,19:16,26:25,27:25}
for rid,r in resources.items():
    if r['template'] in merges:r['template']=merges[r['template']]
for tid,t in templates.items():
    if tid in merges:continue
    t['templatename']=names[tid]
    t['description']='Шаблон типа страницы. Общие части — в чанках, данные — в полях ресурса и ClientConfig.'
plan=dict(templates=[t for tid,t in templates.items() if tid not in merges],remove_templates=list(merges),resource_templates={str(rid):r['template'] for rid,r in resources.items() if r['template']},chunks=chunks,tvs=list(tvs.values()),values=values,contents=contents)
(root/'.tmp-refactor-plan.json').write_text(json.dumps(plan,ensure_ascii=False,indent=2),encoding='utf-8')
print('Prepared',len(plan['templates']),'templates,',len(chunks),'chunks,',len(tvs),'TV definitions')
