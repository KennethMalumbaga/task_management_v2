--
-- PostgreSQL database dump
--

\restrict GOKy15aotHGKGJhaDEGiyHU35Ur2ExtmhvfgTGphlJUyi7chvcgDgxdw3388ilW

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

-- Started on 2026-02-08 23:53:03

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 219 (class 1259 OID 17147)
-- Name: attendance; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance (
    id integer NOT NULL,
    user_id integer,
    att_date date,
    total_hours numeric(5,2) DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    time_in time without time zone,
    time_out time without time zone
);


ALTER TABLE public.attendance OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 17153)
-- Name: attendance_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_id_seq OWNER TO postgres;

--
-- TOC entry 5198 (class 0 OID 0)
-- Dependencies: 220
-- Name: attendance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_id_seq OWNED BY public.attendance.id;


--
-- TOC entry 221 (class 1259 OID 17154)
-- Name: chat_attachments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.chat_attachments (
    attachment_id integer NOT NULL,
    chat_id integer NOT NULL,
    attachment_name character varying(255) NOT NULL
);


ALTER TABLE public.chat_attachments OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 17160)
-- Name: chat_attachments_attachment_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.chat_attachments_attachment_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.chat_attachments_attachment_id_seq OWNER TO postgres;

--
-- TOC entry 5199 (class 0 OID 0)
-- Dependencies: 222
-- Name: chat_attachments_attachment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.chat_attachments_attachment_id_seq OWNED BY public.chat_attachments.attachment_id;


--
-- TOC entry 223 (class 1259 OID 17161)
-- Name: chats; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.chats (
    chat_id integer NOT NULL,
    sender_id integer NOT NULL,
    receiver_id integer NOT NULL,
    message text NOT NULL,
    opened boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.chats OWNER TO postgres;

--
-- TOC entry 224 (class 1259 OID 17172)
-- Name: chats_chat_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.chats_chat_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.chats_chat_id_seq OWNER TO postgres;

--
-- TOC entry 5200 (class 0 OID 0)
-- Dependencies: 224
-- Name: chats_chat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.chats_chat_id_seq OWNED BY public.chats.chat_id;


--
-- TOC entry 242 (class 1259 OID 17366)
-- Name: group_members; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_members (
    id integer NOT NULL,
    group_id integer NOT NULL,
    user_id integer NOT NULL,
    role text DEFAULT 'member'::text,
    created_at timestamp without time zone DEFAULT now(),
    CONSTRAINT group_members_role_check CHECK ((role = ANY (ARRAY['leader'::text, 'member'::text])))
);


ALTER TABLE public.group_members OWNER TO postgres;

--
-- TOC entry 241 (class 1259 OID 17365)
-- Name: group_members_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_members_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_members_id_seq OWNER TO postgres;

--
-- TOC entry 5201 (class 0 OID 0)
-- Dependencies: 241
-- Name: group_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_members_id_seq OWNED BY public.group_members.id;


--
-- TOC entry 246 (class 1259 OID 17416)
-- Name: group_message_attachments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_message_attachments (
    id integer NOT NULL,
    message_id integer NOT NULL,
    attachment_name text NOT NULL,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.group_message_attachments OWNER TO postgres;

--
-- TOC entry 245 (class 1259 OID 17415)
-- Name: group_message_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_message_attachments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_message_attachments_id_seq OWNER TO postgres;

--
-- TOC entry 5202 (class 0 OID 0)
-- Dependencies: 245
-- Name: group_message_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_message_attachments_id_seq OWNED BY public.group_message_attachments.id;


--
-- TOC entry 244 (class 1259 OID 17393)
-- Name: group_messages; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_messages (
    id integer NOT NULL,
    group_id integer NOT NULL,
    sender_id integer NOT NULL,
    message text,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.group_messages OWNER TO postgres;

--
-- TOC entry 243 (class 1259 OID 17392)
-- Name: group_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_messages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_messages_id_seq OWNER TO postgres;

--
-- TOC entry 5203 (class 0 OID 0)
-- Dependencies: 243
-- Name: group_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_messages_id_seq OWNED BY public.group_messages.id;


--
-- TOC entry 240 (class 1259 OID 17354)
-- Name: groups; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.groups (
    id integer NOT NULL,
    name text NOT NULL,
    created_by integer,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.groups OWNER TO postgres;

--
-- TOC entry 239 (class 1259 OID 17353)
-- Name: groups_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.groups_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.groups_id_seq OWNER TO postgres;

--
-- TOC entry 5204 (class 0 OID 0)
-- Dependencies: 239
-- Name: groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.groups_id_seq OWNED BY public.groups.id;


--
-- TOC entry 225 (class 1259 OID 17173)
-- Name: notifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.notifications (
    id integer NOT NULL,
    message text NOT NULL,
    recipient integer,
    type character varying(50) NOT NULL,
    date date DEFAULT CURRENT_DATE,
    is_read boolean DEFAULT false,
    task_id integer
);


ALTER TABLE public.notifications OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 17183)
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.notifications_id_seq OWNER TO postgres;

--
-- TOC entry 5205 (class 0 OID 0)
-- Dependencies: 226
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- TOC entry 227 (class 1259 OID 17184)
-- Name: password_resets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_resets (
    id integer NOT NULL,
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    expires_at timestamp without time zone NOT NULL
);


ALTER TABLE public.password_resets OWNER TO postgres;

--
-- TOC entry 228 (class 1259 OID 17194)
-- Name: password_resets_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.password_resets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.password_resets_id_seq OWNER TO postgres;

--
-- TOC entry 5206 (class 0 OID 0)
-- Dependencies: 228
-- Name: password_resets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.password_resets_id_seq OWNED BY public.password_resets.id;


--
-- TOC entry 229 (class 1259 OID 17195)
-- Name: screenshots; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.screenshots (
    id integer NOT NULL,
    user_id integer,
    attendance_id integer,
    image_path character varying(255) NOT NULL,
    taken_at timestamp without time zone NOT NULL
);


ALTER TABLE public.screenshots OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 17201)
-- Name: screenshots_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.screenshots_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.screenshots_id_seq OWNER TO postgres;

--
-- TOC entry 5207 (class 0 OID 0)
-- Dependencies: 230
-- Name: screenshots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.screenshots_id_seq OWNED BY public.screenshots.id;


--
-- TOC entry 231 (class 1259 OID 17202)
-- Name: subtasks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.subtasks (
    id integer NOT NULL,
    task_id integer NOT NULL,
    member_id integer NOT NULL,
    description text NOT NULL,
    due_date date NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    submission_file character varying(255),
    feedback text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    submission_note text,
    score smallint,
    CONSTRAINT subtasks_score_check CHECK (((score >= 1) AND (score <= 5))),
    CONSTRAINT subtasks_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('submitted'::character varying)::text, ('completed'::character varying)::text, ('revise'::character varying)::text])))
);


ALTER TABLE public.subtasks OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 17217)
-- Name: subtasks_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.subtasks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.subtasks_id_seq OWNER TO postgres;

--
-- TOC entry 5208 (class 0 OID 0)
-- Dependencies: 232
-- Name: subtasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.subtasks_id_seq OWNED BY public.subtasks.id;


--
-- TOC entry 233 (class 1259 OID 17218)
-- Name: task_assignees; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.task_assignees (
    id integer NOT NULL,
    task_id integer,
    user_id integer,
    role text DEFAULT 'member'::text,
    assigned_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT task_assignees_role_check CHECK ((role = ANY (ARRAY['leader'::text, 'member'::text])))
);


ALTER TABLE public.task_assignees OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 17227)
-- Name: task_assignees_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.task_assignees_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.task_assignees_id_seq OWNER TO postgres;

--
-- TOC entry 5209 (class 0 OID 0)
-- Dependencies: 234
-- Name: task_assignees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.task_assignees_id_seq OWNED BY public.task_assignees.id;


--
-- TOC entry 235 (class 1259 OID 17228)
-- Name: tasks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tasks (
    id integer NOT NULL,
    title character varying(100) NOT NULL,
    description text,
    assigned_to integer,
    status text DEFAULT 'pending'::text,
    submission_file character varying(255),
    template_file character varying(255),
    review_comment text,
    reviewed_by integer,
    reviewed_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    due_date date NOT NULL,
    submission_note text,
    rating integer DEFAULT 0,
    CONSTRAINT tasks_status_check CHECK ((status = ANY (ARRAY['pending'::text, 'in_progress'::text, 'completed'::text, 'rejected'::text, 'revise'::text])))
);


ALTER TABLE public.tasks OWNER TO postgres;

--
-- TOC entry 236 (class 1259 OID 17240)
-- Name: tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tasks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tasks_id_seq OWNER TO postgres;

--
-- TOC entry 5210 (class 0 OID 0)
-- Dependencies: 236
-- Name: tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tasks_id_seq OWNED BY public.tasks.id;


--
-- TOC entry 237 (class 1259 OID 17241)
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    full_name character varying(50) NOT NULL,
    username character varying(50) NOT NULL,
    password character varying(255) NOT NULL,
    role text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    phone character varying(20) DEFAULT NULL::character varying,
    address text,
    skills text,
    profile_image character varying(255) DEFAULT 'default.png'::character varying,
    must_change_password boolean DEFAULT false,
    bio text,
    CONSTRAINT users_role_check CHECK ((role = ANY (ARRAY['admin'::text, 'employee'::text])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- TOC entry 238 (class 1259 OID 17256)
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- TOC entry 5211 (class 0 OID 0)
-- Dependencies: 238
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- TOC entry 4921 (class 2604 OID 17257)
-- Name: attendance id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance ALTER COLUMN id SET DEFAULT nextval('public.attendance_id_seq'::regclass);


--
-- TOC entry 4924 (class 2604 OID 17258)
-- Name: chat_attachments attachment_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chat_attachments ALTER COLUMN attachment_id SET DEFAULT nextval('public.chat_attachments_attachment_id_seq'::regclass);


--
-- TOC entry 4925 (class 2604 OID 17259)
-- Name: chats chat_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chats ALTER COLUMN chat_id SET DEFAULT nextval('public.chats_chat_id_seq'::regclass);


--
-- TOC entry 4952 (class 2604 OID 17369)
-- Name: group_members id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members ALTER COLUMN id SET DEFAULT nextval('public.group_members_id_seq'::regclass);


--
-- TOC entry 4957 (class 2604 OID 17419)
-- Name: group_message_attachments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_attachments ALTER COLUMN id SET DEFAULT nextval('public.group_message_attachments_id_seq'::regclass);


--
-- TOC entry 4955 (class 2604 OID 17396)
-- Name: group_messages id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_messages ALTER COLUMN id SET DEFAULT nextval('public.group_messages_id_seq'::regclass);


--
-- TOC entry 4950 (class 2604 OID 17357)
-- Name: groups id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.groups ALTER COLUMN id SET DEFAULT nextval('public.groups_id_seq'::regclass);


--
-- TOC entry 4928 (class 2604 OID 17260)
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- TOC entry 4931 (class 2604 OID 17261)
-- Name: password_resets id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_resets ALTER COLUMN id SET DEFAULT nextval('public.password_resets_id_seq'::regclass);


--
-- TOC entry 4933 (class 2604 OID 17262)
-- Name: screenshots id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.screenshots ALTER COLUMN id SET DEFAULT nextval('public.screenshots_id_seq'::regclass);


--
-- TOC entry 4934 (class 2604 OID 17263)
-- Name: subtasks id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subtasks ALTER COLUMN id SET DEFAULT nextval('public.subtasks_id_seq'::regclass);


--
-- TOC entry 4938 (class 2604 OID 17264)
-- Name: task_assignees id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees ALTER COLUMN id SET DEFAULT nextval('public.task_assignees_id_seq'::regclass);


--
-- TOC entry 4941 (class 2604 OID 17265)
-- Name: tasks id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tasks ALTER COLUMN id SET DEFAULT nextval('public.tasks_id_seq'::regclass);


--
-- TOC entry 4945 (class 2604 OID 17266)
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- TOC entry 5165 (class 0 OID 17147)
-- Dependencies: 219
-- Data for Name: attendance; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance (id, user_id, att_date, total_hours, created_at, time_in, time_out) FROM stdin;
2	3	2026-01-31	0.00	2026-01-31 12:05:33.064402	\N	\N
1	2	2026-01-31	1.88	2026-01-31 11:48:25.542942	\N	\N
3	3	2026-02-01	0.01	2026-02-01 05:01:31.556489	05:01:31	05:02:18
4	7	2026-02-01	0.10	2026-02-01 15:08:41.446039	15:08:41	15:14:44
5	3	2026-02-03	0.00	2026-02-03 17:44:37.91737	17:44:37	\N
6	2	2026-02-06	0.01	2026-02-06 09:38:07.600534	09:38:07	09:38:53
7	2	2026-02-06	0.08	2026-02-06 10:08:54.580157	10:08:54	10:13:28
8	2	2026-02-06	0.06	2026-02-06 10:13:59.575121	10:13:59	10:17:17
9	2	2026-02-07	0.08	2026-02-07 22:18:06.484853	22:18:06	22:23:04
10	2	2026-02-07	0.01	2026-02-07 22:23:32.868798	22:23:32	22:24:04
11	2	2026-02-07	0.01	2026-02-07 22:24:31.396824	22:24:31	22:24:58
12	3	2026-02-07	0.00	2026-02-07 22:27:16.510158	22:27:16	22:27:26
\.


--
-- TOC entry 5167 (class 0 OID 17154)
-- Dependencies: 221
-- Data for Name: chat_attachments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.chat_attachments (attachment_id, chat_id, attachment_name) FROM stdin;
1	29	chat_1770473663_0_2.png
2	30	chat_1770473681_0_2.pdf
3	31	chat_1770473689_1_2.pdf
4	33	chat_1770473727_0_2.png
5	34	chat_1770540615_0_1.pdf
\.


--
-- TOC entry 5169 (class 0 OID 17161)
-- Dependencies: 223
-- Data for Name: chats; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.chats (chat_id, sender_id, receiver_id, message, opened, created_at) FROM stdin;
6	2	3	dele lang	t	2026-01-31 22:47:01.757222
9	2	3	baaaaiiii	t	2026-01-31 23:23:25.560513
7	1	2	kol, need daw mag fix sa database kay ang lms na database need i fix kay para ma fix try ra nimo ug fix unya dugay2 kay para ma fix na dayun, goods raman gud if ma fix na pero i fix gyyud na kay para ma fix na siya ba, goods raman na basta ma fix gyud	t	2026-01-31 23:04:23.812436
8	1	2	Parrrrtttttt!	t	2026-01-31 23:15:45.975327
5	3	2	red horse	t	2026-01-31 22:46:34.240058
10	3	2	parrrrt	t	2026-01-31 23:28:31.663092
11	2	3	uy partttt	t	2026-01-31 23:31:13.114131
12	3	2	parrrrrrrtttttttttt!!	t	2026-01-31 23:35:48.330833
13	2	3	papaarttttt!	t	2026-01-31 23:37:11.351512
14	3	2	parttttt ang project	t	2026-01-31 23:42:08.214768
15	2	3	ayaw sa to partttt	t	2026-01-31 23:46:12.591742
16	3	2	naaaaaaa	t	2026-01-31 23:47:06.493739
17	3	2	patays gid ta	t	2026-01-31 23:47:09.163463
18	3	2	ayaw ana parttt	t	2026-01-31 23:47:11.726063
2	2	1	edi wow boss	t	2026-01-31 22:12:46.862193
19	1	2	sir tapos na po	t	2026-02-01 02:18:07.387077
20	1	3	Ppartsssss kumusta na partt?	t	2026-02-01 04:14:56.505885
21	1	3	project nato part?	t	2026-02-01 04:15:02.994175
22	3	1	goods ra part	t	2026-02-01 04:17:56.700886
4	2	4	hi lodz IS ka?	t	2026-01-31 22:15:41.269449
23	1	3	Good morning boss! Ang project	t	2026-02-01 09:20:19.627125
24	1	2	parts, ang project daw	t	2026-02-01 09:20:46.356442
1	1	2	hey jude	t	2026-01-31 22:04:03.712662
3	1	2	larga bola	t	2026-01-31 22:15:00.295049
25	1	4	kumusta naman ang project?	t	2026-02-01 15:14:33.983216
26	4	1	Working pa sir	t	2026-02-01 15:15:07.777513
27	1	4	buhata na imo task win	t	2026-02-06 10:03:24.673483
28	2	4		t	2026-02-07 22:12:17.813126
29	2	4		t	2026-02-07 22:14:23.605808
30	2	4		t	2026-02-07 22:14:41.969115
31	2	4		t	2026-02-07 22:14:49.764653
32	2	4		t	2026-02-07 22:15:16.078529
33	2	4		t	2026-02-07 22:15:27.531529
34	1	3	ani go	t	2026-02-08 16:50:15.85789
\.


--
-- TOC entry 5188 (class 0 OID 17366)
-- Dependencies: 242
-- Data for Name: group_members; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.group_members (id, group_id, user_id, role, created_at) FROM stdin;
1	1	4	leader	2026-02-08 23:23:11.486906
2	1	6	member	2026-02-08 23:23:11.501166
3	1	5	member	2026-02-08 23:23:11.501759
4	1	2	member	2026-02-08 23:23:11.502401
5	2	4	leader	2026-02-08 23:32:14.580148
6	2	6	member	2026-02-08 23:32:14.59262
7	2	2	member	2026-02-08 23:32:14.594648
8	2	5	member	2026-02-08 23:32:14.596423
\.


--
-- TOC entry 5192 (class 0 OID 17416)
-- Dependencies: 246
-- Data for Name: group_message_attachments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.group_message_attachments (id, message_id, attachment_name, created_at) FROM stdin;
\.


--
-- TOC entry 5190 (class 0 OID 17393)
-- Dependencies: 244
-- Data for Name: group_messages; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.group_messages (id, group_id, sender_id, message, created_at) FROM stdin;
\.


--
-- TOC entry 5186 (class 0 OID 17354)
-- Dependencies: 240
-- Data for Name: groups; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.groups (id, name, created_by, created_at) FROM stdin;
1	LMS NS DNSC	4	2026-02-08 23:23:11.472478
2	LMS NS DNSC	4	2026-02-08 23:32:14.56999
\.


--
-- TOC entry 5171 (class 0 OID 17173)
-- Dependencies: 225
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.notifications (id, message, recipient, type, date, is_read, task_id) FROM stdin;
158	You have been assigned a subtask for: Revamp fireguard	3	New Subtask	2026-02-03	f	42
160	Subtask submitted by User 3	4	Subtask Submitted	2026-02-03	f	42
162	Your subtask submission has been ACCEPTED. Score: 5/5.	2	Subtask Review	2026-02-03	f	42
164	Subtask submitted by User 4	4	Subtask Submitted	2026-02-03	f	42
166	Task Submitted by Leader ()	1	Task Submitted	2026-02-03	f	42
167	Task Submitted by Leader ()	7	Task Submitted	2026-02-03	f	42
171	Task Accepted & Rated (5/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
172	Task Accepted & Rated (5/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
173	Task Accepted & Rated (5/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
1	'1' has been assigned to you as leader. Please review and start working on it	2	New Task Assigned	2026-01-31	t	\N
4	1 submitted by roel descartin	1	Task Submitted	2026-01-31	t	\N
5	'1' has been approved and marked as completed. 	2	Task Completed	2026-01-31	f	\N
89	Task Accepted & Rated (5/5): 1	2	Task Verified	2026-02-01	f	\N
154	'Revamp fireguard' has been assigned to you as leader. Please review and start working on it	4	New Task Assigned	2026-02-03	f	42
155	'Revamp fireguard' has been assigned to you. Please review and start working on it	2	New Task Assigned	2026-02-03	f	42
156	'Revamp fireguard' has been assigned to you. Please review and start working on it	3	New Task Assigned	2026-02-03	f	42
159	Subtask submitted by User 2	4	Subtask Submitted	2026-02-03	f	42
161	Your subtask submission has been ACCEPTED. Score: 4/5.	3	Subtask Review	2026-02-03	f	42
163	You have been assigned a subtask for: Revamp fireguard	4	New Subtask	2026-02-03	f	42
165	Your subtask submission has been ACCEPTED. Score: 5/5.	4	Subtask Review	2026-02-03	f	42
168	Task Accepted & Rated (5/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
169	Task Accepted & Rated (5/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
170	Task Accepted & Rated (5/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
48	'task 5' has been assigned to you. Please review and start working on it	2	New Task Assigned	2026-01-31	f	\N
49	'task 5' has been assigned to you. Please review and start working on it	3	New Task Assigned	2026-01-31	f	\N
47	'task 5' has been assigned to you as leader. Please review and start working on it	4	New Task Assigned	2026-01-31	t	\N
50	You have been assigned a subtask for: task 5	2	New Subtask	2026-01-31	f	\N
51	You have been assigned a subtask for: task 5	3	New Subtask	2026-01-31	f	\N
52	Subtask submitted by User 2	4	Subtask Submitted	2026-01-31	f	\N
53	Subtask submitted by User 3	4	Subtask Submitted	2026-01-31	f	\N
54	Your subtask submission has been ACCEPTED.	3	Subtask Review	2026-01-31	f	\N
55	Your subtask submission requires REVISION. Feedback: kulang pa lods	2	Subtask Review	2026-01-31	f	\N
56	Subtask submitted by User 2	4	Subtask Submitted	2026-01-31	f	\N
57	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-01-31	f	\N
58	task 5 submitted by sherwin españo	1	Task Submitted	2026-01-31	f	\N
59	'task 5' has been ACCEPTED.	4	Task Review	2026-01-31	f	\N
63	'task 5' has been approved and marked as completed. 	4	Task Completed	2026-02-01	f	\N
64	'task 5' has been approved and marked as completed. 	4	Task Completed	2026-02-01	f	\N
84	Task Accepted & Rated (5/5): task 5	4	Task Verified	2026-02-01	f	\N
85	Task Accepted & Rated (5/5): task 5	2	Task Verified	2026-02-01	f	\N
60	'latest task' has been assigned to you as leader. Please review and start working on it	3	New Task Assigned	2026-01-31	f	\N
61	'latest task' has been assigned to you. Please review and start working on it	4	New Task Assigned	2026-01-31	f	\N
62	'latest task' has been assigned to you. Please review and start working on it	2	New Task Assigned	2026-01-31	f	\N
86	Task Revision Requested: latest task	4	Task Revision	2026-02-01	f	\N
87	Task Revision Requested: latest task	2	Task Revision	2026-02-01	f	\N
88	Task Revision Requested: latest task	3	Task Revision	2026-02-01	f	\N
99	You have been assigned a subtask for: latest task	4	New Subtask	2026-02-01	f	\N
100	Subtask submitted by User 4	3	Subtask Submitted	2026-02-01	f	\N
101	Your subtask submission has been ACCEPTED.	4	Subtask Review	2026-02-01	f	\N
102	Task Submitted by Leader ()	1	Task Submitted	2026-02-01	f	\N
103	Task Accepted & Rated (5/5): latest task	4	Task Verified	2026-02-01	f	\N
104	Task Accepted & Rated (5/5): latest task	2	Task Verified	2026-02-01	f	\N
105	Task Accepted & Rated (5/5): latest task	3	Task Verified	2026-02-01	f	\N
7	'task 4' has been assigned to you. Please review and start working on it	2	New Task Assigned	2026-01-31	f	\N
6	'task 4' has been assigned to you as leader. Please review and start working on it	3	New Task Assigned	2026-01-31	t	\N
8	You have been assigned a subtask for: task 4	2	New Subtask	2026-01-31	t	\N
40	Subtask submitted by User 2	3	Subtask Submitted	2026-01-31	f	\N
41	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-01-31	f	\N
42	task 4 submitted by neljhan redondo	1	Task Submitted	2026-01-31	f	\N
43	You have been assigned a subtask for: task 4	2	New Subtask	2026-01-31	f	\N
44	Subtask submitted by User 2	3	Subtask Submitted	2026-01-31	f	\N
45	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-01-31	f	\N
46	'task 4' has been ACCEPTED.	3	Task Review	2026-01-31	f	\N
106	Task Accepted & Rated (5/5): task 4	2	Task Verified	2026-02-01	f	\N
107	Task Accepted & Rated (5/5): task 4	3	Task Verified	2026-02-01	f	\N
2	'task 2' has been assigned to you as leader. Please review and start working on it	3	New Task Assigned	2026-01-31	f	\N
3	'task 2' has been assigned to you. Please review and start working on it	2	New Task Assigned	2026-01-31	f	\N
39	You have been assigned a subtask for: task 2	2	New Subtask	2026-01-31	t	\N
65	'task 2' has been updated. 	3	Task Updated	2026-02-01	f	\N
66	'task 2' has been updated. 	2	Task Updated	2026-02-01	f	\N
67	'task 2' has been updated. 	3	Task Updated	2026-02-01	f	\N
68	You have been assigned a subtask for: task 2	2	New Subtask	2026-02-01	f	\N
69	Subtask submitted by User 2	3	Subtask Submitted	2026-02-01	f	\N
70	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-02-01	f	\N
71	Subtask submitted by User 2	3	Subtask Submitted	2026-02-01	f	\N
72	Your subtask submission requires REVISION. Feedback: usba boss pangit man	2	Subtask Review	2026-02-01	f	\N
73	Subtask submitted by User 2	3	Subtask Submitted	2026-02-01	f	\N
74	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-02-01	f	\N
75	You have been assigned a subtask for: task 2	2	New Subtask	2026-02-01	f	\N
76	Subtask submitted by User 2	3	Subtask Submitted	2026-02-01	f	\N
77	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-02-01	f	\N
78	You have been assigned a subtask for: task 2	2	New Subtask	2026-02-01	f	\N
79	Subtask submitted by User 2	3	Subtask Submitted	2026-02-01	f	\N
80	Your subtask submission requires REVISION. Feedback: dili, usab	2	Subtask Review	2026-02-01	f	\N
81	Subtask submitted by User 2	3	Subtask Submitted	2026-02-01	f	\N
82	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-02-01	f	\N
83	Task Submitted by Leader ()	1	Task Submitted	2026-02-01	f	\N
108	Task Accepted & Rated (3/5): task 2	2	Task Verified	2026-02-01	f	\N
109	Task Accepted & Rated (3/5): task 2	3	Task Verified	2026-02-01	f	\N
110	Task Accepted & Rated (3/5): task 2	4	Task Verified	2026-02-01	f	\N
90	'JBL speaker repair' has been assigned to you as leader. Please review and start working on it	2	New Task Assigned	2026-02-01	f	\N
91	'JBL speaker repair' has been assigned to you. Please review and start working on it	3	New Task Assigned	2026-02-01	f	\N
92	You have been assigned a subtask for: JBL speaker repair	2	New Subtask	2026-02-01	f	\N
93	Subtask submitted by User 2	2	Subtask Submitted	2026-02-01	f	\N
94	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-02-01	f	\N
95	Task Submitted by Leader ()	1	Task Submitted	2026-02-01	f	\N
96	Task Revision Requested: JBL speaker repair	2	Task Revision	2026-02-01	f	\N
97	Task Revision Requested: JBL speaker repair	3	Task Revision	2026-02-01	f	\N
98	Task Resubmitted by Leader ()	1	Task Resubmitted	2026-02-01	f	\N
114	Task Accepted & Rated (5/5): JBL speaker repair	2	Task Verified	2026-02-01	f	\N
115	Task Accepted & Rated (5/5): JBL speaker repair	3	Task Verified	2026-02-01	f	\N
111	'LMS' has been assigned to you as leader. Please review and start working on it	2	New Task Assigned	2026-02-01	f	\N
112	'LMS' has been assigned to you. Please review and start working on it	4	New Task Assigned	2026-02-01	f	\N
113	'LMS' has been assigned to you. Please review and start working on it	3	New Task Assigned	2026-02-01	f	\N
116	You have been assigned a subtask for: LMS	4	New Subtask	2026-02-01	f	\N
117	Subtask submitted by User 4	2	Subtask Submitted	2026-02-01	f	\N
118	Your subtask submission requires REVISION. Feedback: usba kay mali	4	Subtask Review	2026-02-01	f	\N
119	Subtask submitted by User 4	2	Subtask Submitted	2026-02-01	f	\N
120	Your subtask submission has been ACCEPTED.	4	Subtask Review	2026-02-03	f	\N
124	Task Submitted by Leader ()	1	Task Submitted	2026-02-03	f	\N
125	Task Submitted by Leader ()	7	Task Submitted	2026-02-03	f	\N
126	Task Accepted & Rated (5/5): LMS	2	Task Verified	2026-02-03	f	\N
127	Task Accepted & Rated (5/5): LMS	4	Task Verified	2026-02-03	f	\N
128	Task Accepted & Rated (5/5): LMS	3	Task Verified	2026-02-03	f	\N
121	You have been assigned a subtask for: new task	2	New Subtask	2026-02-03	f	\N
122	Subtask submitted by User 2	2	Subtask Submitted	2026-02-03	f	\N
123	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-02-03	f	\N
129	You have been assigned a subtask for: new task	2	New Subtask	2026-02-03	f	\N
130	Subtask submitted by User 2	2	Subtask Submitted	2026-02-03	f	\N
131	Your subtask submission has been ACCEPTED.	2	Subtask Review	2026-02-03	f	\N
132	Task Submitted by Leader ()	1	Task Submitted	2026-02-03	f	\N
133	Task Submitted by Leader ()	7	Task Submitted	2026-02-03	f	\N
134	Task Accepted & Rated (5/5): new task	2	Task Verified	2026-02-03	f	\N
157	You have been assigned a subtask for: Revamp fireguard	2	New Subtask	2026-02-03	f	42
174	Task Accepted & Rated (5/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
175	Task Accepted & Rated (5/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
176	Task Accepted & Rated (5/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
177	Task Accepted & Rated (3/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
178	Task Accepted & Rated (3/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
179	Task Accepted & Rated (3/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
180	Task Accepted & Rated (5/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
181	Task Accepted & Rated (5/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
182	Task Accepted & Rated (5/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
183	Task Accepted & Rated (1/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
184	Task Accepted & Rated (1/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
185	Task Accepted & Rated (1/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
186	Task Accepted & Rated (1/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
187	Task Accepted & Rated (1/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
188	Task Accepted & Rated (1/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
189	Task Accepted & Rated (1/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
190	Task Accepted & Rated (1/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
191	Task Accepted & Rated (1/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
192	Task Accepted & Rated (5/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
193	Task Accepted & Rated (5/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
194	Task Accepted & Rated (5/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
195	Task Revision Requested: Revamp fireguard	2	Task Revision	2026-02-04	f	42
196	Task Revision Requested: Revamp fireguard	4	Task Revision	2026-02-04	f	42
197	Task Revision Requested: Revamp fireguard	3	Task Revision	2026-02-04	f	42
198	Task Resubmitted by Leader ()	1	Task Resubmitted	2026-02-04	f	42
199	Task Resubmitted by Leader ()	7	Task Resubmitted	2026-02-04	f	42
200	Task Revision Requested: Revamp fireguard	2	Task Revision	2026-02-04	f	42
201	Task Revision Requested: Revamp fireguard	4	Task Revision	2026-02-04	f	42
202	Task Revision Requested: Revamp fireguard	3	Task Revision	2026-02-04	f	42
203	Task Resubmitted by Leader ()	1	Task Resubmitted	2026-02-04	f	42
204	Task Resubmitted by Leader ()	7	Task Resubmitted	2026-02-04	f	42
205	Task Accepted & Rated (5/5): Revamp fireguard	2	Task Verified	2026-02-04	f	42
206	Task Accepted & Rated (5/5): Revamp fireguard	4	Task Verified	2026-02-04	f	42
207	Task Accepted & Rated (5/5): Revamp fireguard	3	Task Verified	2026-02-04	f	42
146	Subtask submitted by User 5	3	Subtask Submitted	2026-02-03	f	\N
150	Task Accepted & Rated (5/5): Web app for solar power generator system	3	Task Verified	2026-02-03	f	\N
151	Task Accepted & Rated (5/5): Web app for solar power generator system	2	Task Verified	2026-02-03	f	\N
152	Task Accepted & Rated (5/5): Web app for solar power generator system	4	Task Verified	2026-02-03	f	\N
153	Task Accepted & Rated (5/5): Web app for solar power generator system	5	Task Verified	2026-02-03	f	\N
147	Your subtask submission has been ACCEPTED. Score: 5/5.	5	Subtask Review	2026-02-03	f	\N
135	'Web app for solar power generator system' has been assigned to you as leader. Please review and start working on it	3	New Task Assigned	2026-02-03	f	\N
136	'Web app for solar power generator system' has been assigned to you. Please review and start working on it	2	New Task Assigned	2026-02-03	f	\N
137	'Web app for solar power generator system' has been assigned to you. Please review and start working on it	4	New Task Assigned	2026-02-03	f	\N
138	'Web app for solar power generator system' has been assigned to you. Please review and start working on it	5	New Task Assigned	2026-02-03	f	\N
139	You have been assigned a subtask for: Web app for solar power generator system	4	New Subtask	2026-02-03	f	\N
140	You have been assigned a subtask for: Web app for solar power generator system	4	New Subtask	2026-02-03	f	\N
141	You have been assigned a subtask for: Web app for solar power generator system	5	New Subtask	2026-02-03	f	\N
142	Subtask submitted by User 4	3	Subtask Submitted	2026-02-03	f	\N
143	Your subtask submission has been ACCEPTED.	4	Subtask Review	2026-02-03	f	\N
144	Subtask submitted by User 4	3	Subtask Submitted	2026-02-03	f	\N
145	Your subtask submission has been ACCEPTED. Score: 5/5.	4	Subtask Review	2026-02-03	f	\N
148	Task Submitted by Leader ()	1	Task Submitted	2026-02-03	f	\N
149	Task Submitted by Leader ()	7	Task Submitted	2026-02-03	f	\N
208	'New Task' has been assigned to you as leader. Please review and start working on it	4	New Task Assigned	2026-02-06	f	43
209	'New Task' has been assigned to you. Please review and start working on it	2	New Task Assigned	2026-02-06	f	43
210	'new task 2' has been assigned to you as leader. Please review and start working on it	2	New Task Assigned	2026-02-06	f	44
211	You have been assigned a subtask for: new task 2	2	New Subtask	2026-02-07	f	44
212	'LMS' has been assigned to you as leader. Please review and start working on it	4	New Task Assigned	2026-02-08	f	45
213	'LMS' has been assigned to you. Please review and start working on it	6	New Task Assigned	2026-02-08	f	45
214	'LMS' has been assigned to you. Please review and start working on it	2	New Task Assigned	2026-02-08	f	45
215	'LMS' has been assigned to you. Please review and start working on it	5	New Task Assigned	2026-02-08	f	45
\.


--
-- TOC entry 5173 (class 0 OID 17184)
-- Dependencies: 227
-- Data for Name: password_resets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_resets (id, email, token, created_at, expires_at) FROM stdin;
\.


--
-- TOC entry 5175 (class 0 OID 17195)
-- Dependencies: 229
-- Data for Name: screenshots; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.screenshots (id, user_id, attendance_id, image_path, taken_at) FROM stdin;
138	2	6	screenshots/2_6_1770341890_6985460289faa.png	2026-02-06 09:38:10.570167
139	2	6	screenshots/2_6_1770341915_6985461b68f40.png	2026-02-06 09:38:35.431095
140	2	7	screenshots/2_7_1770343787_69854d6b5d4cb.png	2026-02-06 10:09:47.383608
141	2	7	screenshots/2_7_1770343813_69854d8551c8b.png	2026-02-06 10:10:13.336255
142	2	7	screenshots/2_7_1770343840_69854da051205.png	2026-02-06 10:10:40.333583
143	2	7	screenshots/2_7_1770343865_69854db94fe19.png	2026-02-06 10:11:05.328431
144	2	7	screenshots/2_7_1770343894_69854dd64f6cd.png	2026-02-06 10:11:34.326511
145	2	7	screenshots/2_7_1770343922_69854df24feb6.png	2026-02-06 10:12:02.328625
146	2	7	screenshots/2_7_1770343947_69854e0b518ec.png	2026-02-06 10:12:27.335514
147	2	7	screenshots/2_7_1770343969_69854e2150701.png	2026-02-06 10:12:49.330729
148	4	7	screenshots/4_7_1770343992_69854e3850825.png	2026-02-06 10:13:12.331434
149	2	7	screenshots/2_7_1770344016_69854e5050b38.png	2026-02-06 10:13:36.331707
150	2	8	screenshots/2_8_1770344046_69854e6ee245d.png	2026-02-06 10:14:06.92804
151	2	8	screenshots/2_8_1770344068_69854e8451c7f.png	2026-02-06 10:14:28.336259
152	2	8	screenshots/2_8_1770344097_69854ea15153f.png	2026-02-06 10:14:57.334319
153	2	8	screenshots/2_8_1770344123_69854ebb6e783.png	2026-02-06 10:15:23.453691
154	2	8	screenshots/2_8_1770344149_69854ed5521a7.png	2026-02-06 10:15:49.337497
155	2	8	screenshots/2_8_1770344179_69854ef3519b9.png	2026-02-06 10:16:19.33568
156	2	8	screenshots/2_8_1770344208_69854f106a651.png	2026-02-06 10:16:48.437005
157	2	8	screenshots/2_8_1770344233_69854f294f8de.png	2026-02-06 10:17:13.328239
158	2	8	screenshots/2_8_1770344258_69854f4269171.png	2026-02-06 10:17:38.431732
159	2	8	screenshots/2_8_1770344286_69854f5e7c3cf.png	2026-02-06 10:18:06.510983
160	2	8	screenshots/2_8_1770344313_69854f7953fb1.png	2026-02-06 10:18:33.345771
161	2	8	screenshots/2_8_1770344340_69854f94542c1.png	2026-02-06 10:19:00.346312
162	2	8	screenshots/2_8_1770344367_69854faf52afd.png	2026-02-06 10:19:27.340299
163	2	8	screenshots/2_8_1770344394_69854fca510ff.png	2026-02-06 10:19:54.33394
164	2	8	screenshots/2_8_1770344416_69854fe053442.png	2026-02-06 10:20:16.342464
165	2	8	screenshots/2_8_1770344438_69854ff6517b0.png	2026-02-06 10:20:38.335702
166	2	8	screenshots/2_8_1770344463_6985500f5106d.png	2026-02-06 10:21:03.333215
167	2	8	screenshots/2_8_1770344484_69855024527b4.png	2026-02-06 10:21:24.339508
168	2	8	screenshots/2_8_1770344511_6985503f54747.png	2026-02-06 10:21:51.347266
169	2	8	screenshots/2_8_1770344537_698550596a28e.png	2026-02-06 10:22:17.436127
170	2	8	screenshots/2_8_1770344565_698550755300b.png	2026-02-06 10:22:45.341737
171	2	8	screenshots/2_8_1770344593_69855091522cb.png	2026-02-06 10:23:13.33795
172	2	8	screenshots/2_8_1770344618_698550aa6d614.png	2026-02-06 10:23:38.44982
173	2	8	screenshots/2_8_1770344640_698550c052ae2.png	2026-02-06 10:24:00.340179
174	2	8	screenshots/2_8_1770344663_698550d75322c.png	2026-02-06 10:24:23.342123
175	2	8	screenshots/2_8_1770344693_698550f5519a5.png	2026-02-06 10:24:53.335571
176	2	8	screenshots/2_8_1770344719_6985510f501be.png	2026-02-06 10:25:19.329381
177	2	8	screenshots/2_8_1770344742_69855126538a1.png	2026-02-06 10:25:42.343717
178	2	8	screenshots/2_8_1770344767_6985513f55463.png	2026-02-06 10:26:07.351092
179	2	8	screenshots/2_8_1770344794_6985515a55fda.png	2026-02-06 10:26:34.353643
180	2	8	screenshots/2_8_1770344819_6985517355f2a.png	2026-02-06 10:26:59.353467
181	2	8	screenshots/2_8_1770344847_6985518f555a7.png	2026-02-06 10:27:27.351047
182	2	8	screenshots/2_8_1770344868_698551a455c9e.png	2026-02-06 10:27:48.3534
183	2	8	screenshots/2_8_1770344893_698551bd553d2.png	2026-02-06 10:28:13.350549
184	2	8	screenshots/2_8_1770344918_698551d6570db.png	2026-02-06 10:28:38.358105
185	2	8	screenshots/2_8_1770344940_698551ec6d82a.png	2026-02-06 10:29:00.450644
186	2	8	screenshots/2_8_1770344969_6985520955744.png	2026-02-06 10:29:29.352076
187	2	8	screenshots/2_8_1770344991_6985521f57304.png	2026-02-06 10:29:51.358909
188	2	8	screenshots/2_8_1770345020_6985523c566f3.png	2026-02-06 10:30:20.356012
189	2	8	screenshots/2_8_1770345045_698552556e3f0.png	2026-02-06 10:30:45.453033
190	2	9	screenshots/2_9_1770474026_69874a2aefff0.png	2026-02-07 22:20:26.986447
191	2	9	screenshots/2_9_1770474047_69874a3f4e684.png	2026-02-07 22:20:47.322528
192	2	9	screenshots/2_9_1770474077_69874a5d6c3d4.png	2026-02-07 22:21:17.444664
193	2	9	screenshots/2_9_1770474105_69874a7969130.png	2026-02-07 22:21:45.431971
194	2	9	screenshots/2_9_1770474129_69874a914db10.png	2026-02-07 22:22:09.319676
195	2	9	screenshots/2_9_1770474157_69874aad4de12.png	2026-02-07 22:22:37.320592
196	2	10	screenshots/2_10_1770474216_69874ae810e63.png	2026-02-07 22:23:36.071613
197	2	10	screenshots/2_10_1770474242_69874b0250410.png	2026-02-07 22:24:02.330175
198	2	11	screenshots/2_11_1770474275_69874b23f2c48.png	2026-02-07 22:24:35.995628
199	3	12	screenshots/3_12_1770474439_69874bc71dc59.png	2026-02-07 22:27:19.124481
\.


--
-- TOC entry 5177 (class 0 OID 17202)
-- Dependencies: 231
-- Data for Name: subtasks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.subtasks (id, task_id, member_id, description, due_date, status, submission_file, feedback, created_at, updated_at, submission_note, score) FROM stdin;
50	42	3	UI/UX design	2026-02-05	completed	uploads/subtask_50_1770118983.jpg	payts sya bai	2026-02-03 19:41:59.923679	2026-02-03 19:43:44.866678	goods nani	4
49	42	2	Authentication	2026-02-05	completed	uploads/subtask_49_1770118945.jpg	goods	2026-02-03 19:41:38.80671	2026-02-03 19:43:56.111577	payts nani boi	5
51	42	4	Documentation	2026-02-05	completed	uploads/subtask_51_1770133650.jpg	nice super fast	2026-02-03 23:47:01.82799	2026-02-03 23:47:51.95633	mao nani boss	5
52	44	2	code	2026-03-04	pending	\N	\N	2026-02-07 21:37:53.08837	2026-02-07 21:37:53.08837	\N	\N
\.


--
-- TOC entry 5179 (class 0 OID 17218)
-- Dependencies: 233
-- Data for Name: task_assignees; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.task_assignees (id, task_id, user_id, role, assigned_at) FROM stdin;
63	42	4	leader	2026-02-03 19:41:04.172199
64	42	2	member	2026-02-03 19:41:04.174737
65	42	3	member	2026-02-03 19:41:04.175352
66	43	4	leader	2026-02-06 10:00:54.477357
67	43	2	member	2026-02-06 10:00:54.479743
68	44	2	leader	2026-02-06 10:03:01.398315
69	45	4	leader	2026-02-08 23:24:18.707037
70	45	6	member	2026-02-08 23:24:18.711474
71	45	2	member	2026-02-08 23:24:18.712108
72	45	5	member	2026-02-08 23:24:18.712569
\.


--
-- TOC entry 5181 (class 0 OID 17228)
-- Dependencies: 235
-- Data for Name: tasks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tasks (id, title, description, assigned_to, status, submission_file, template_file, review_comment, reviewed_by, reviewed_at, created_at, due_date, submission_note, rating) FROM stdin;
42	Revamp fireguard	usaba ang ui boi	4	completed	uploads/task_42_resubmit_1770138774.zip	uploads/template_1770118864_20260202_192012-COLLAGE.jpg	omkeh	1	2026-02-04 01:17:39.979204	2026-02-03 19:41:04.152332	2026-02-13	please ko lods bi	5
43	New Task	new task	4	pending	\N	uploads/template_1770343254_Españo-Resume.pdf	\N	\N	\N	2026-02-06 10:00:54.473494	2026-03-28	\N	0
44	new task 2	new task 2	2	pending	\N	\N	\N	\N	\N	2026-02-06 10:03:01.395903	2026-02-28	\N	0
45	LMS	School management and learning system	4	pending	\N	\N	\N	\N	\N	2026-02-08 23:24:18.698305	2026-02-18	\N	0
\.


--
-- TOC entry 5183 (class 0 OID 17241)
-- Dependencies: 237
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, full_name, username, password, role, created_at, phone, address, skills, profile_image, must_change_password, bio) FROM stdin;
4	sherwin españo	sherwin	$2y$10$AzUENDasiWysdYgs3nt8HuvQLrIdImxyZxeoRQwc3zFZ0jF/5y6Ue	employee	2026-01-31 14:26:27.486474	09123456789	Barangay Tibungco Davao City	Documentation, n8n automation	IMG-697eaca030a2f2.91783698.jpg	f	\N
1	Admin ako	admin	$2y$10$b/v2OHMZLbahxklajBoPguDE4JtJiSN4k84v4CCZSHZ8Bpd1MYbwS	admin	2026-01-31 10:55:22.536092	09123456789	sa lugar na wala ka	skill 1 ni ling	IMG-697ebe16f28190.33379626.jpg	f	\N
6	Neel Dzan	rneljhan@gmail.com	$2y$10$XdWs39MS100kDG4aM/S.h.XVZ/aHi6RLSmquAR4C9YeUh9YPDpzou	employee	2026-02-01 12:29:47.127224	09123456789	sa lugar na wala ka	Vibe coderz	IMG-697ed704e23446.88942558.png	f	
7	Mark Mallari	cornerstonelandd@gmail.com	$2y$10$fC87VHBGAcaeDfRYMF/gWOuYIpnzdKTFTcjMWyap1RNhB4YELPAwq	admin	2026-02-01 15:04:49.112675				IMG-697efbe732eb16.84398212.jpg	f	
5	So Gool	sogolanagood0@gmail.com	$2y$10$1gu1j1feYf6bCT16lyiU9erbcROImD7xHsvsVvxdPj.NSRWsQSZ2S	employee	2026-02-01 12:01:36.97927	09123456789	sa lugar na wala ka	Vibe coderz	IMG-697ed0ba5dadd2.15741140.png	f	
2	roel descartin	roel	$2y$10$x4DWkHcB7.NIak2Z6jYyoegDMmOT3cXEqFjW1Y/x0rZNGCKo3ZtBe	employee	2026-01-31 11:46:55.600128	09123456789	sa lugar na wala ka	Iot Developer, Networking	IMG-69833bd30932e7.08823651.png	f	
3	neljhan redondo	jhan2	$2y$10$noIr80s338bHtEP83qG3Fuu2GzEbXefkVdS1K1ayDDzzE7HZGagaq	employee	2026-01-31 12:01:42.598559	09854747065	Barangay A. O. Floirendo, Panabo City, Davao del Norte	UI/UX Designer, Front-end Developer	IMG-697ea8e91f5eb4.10559923.jpg	f	\N
\.


--
-- TOC entry 5212 (class 0 OID 0)
-- Dependencies: 220
-- Name: attendance_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_id_seq', 12, true);


--
-- TOC entry 5213 (class 0 OID 0)
-- Dependencies: 222
-- Name: chat_attachments_attachment_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.chat_attachments_attachment_id_seq', 5, true);


--
-- TOC entry 5214 (class 0 OID 0)
-- Dependencies: 224
-- Name: chats_chat_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.chats_chat_id_seq', 34, true);


--
-- TOC entry 5215 (class 0 OID 0)
-- Dependencies: 241
-- Name: group_members_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.group_members_id_seq', 8, true);


--
-- TOC entry 5216 (class 0 OID 0)
-- Dependencies: 245
-- Name: group_message_attachments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.group_message_attachments_id_seq', 1, false);


--
-- TOC entry 5217 (class 0 OID 0)
-- Dependencies: 243
-- Name: group_messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.group_messages_id_seq', 1, false);


--
-- TOC entry 5218 (class 0 OID 0)
-- Dependencies: 239
-- Name: groups_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.groups_id_seq', 2, true);


--
-- TOC entry 5219 (class 0 OID 0)
-- Dependencies: 226
-- Name: notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.notifications_id_seq', 215, true);


--
-- TOC entry 5220 (class 0 OID 0)
-- Dependencies: 228
-- Name: password_resets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.password_resets_id_seq', 7, true);


--
-- TOC entry 5221 (class 0 OID 0)
-- Dependencies: 230
-- Name: screenshots_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.screenshots_id_seq', 199, true);


--
-- TOC entry 5222 (class 0 OID 0)
-- Dependencies: 232
-- Name: subtasks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.subtasks_id_seq', 52, true);


--
-- TOC entry 5223 (class 0 OID 0)
-- Dependencies: 234
-- Name: task_assignees_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.task_assignees_id_seq', 72, true);


--
-- TOC entry 5224 (class 0 OID 0)
-- Dependencies: 236
-- Name: tasks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tasks_id_seq', 45, true);


--
-- TOC entry 5225 (class 0 OID 0)
-- Dependencies: 238
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 7, true);


--
-- TOC entry 4966 (class 2606 OID 17268)
-- Name: attendance attendance_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance
    ADD CONSTRAINT attendance_pkey PRIMARY KEY (id);


--
-- TOC entry 4968 (class 2606 OID 17270)
-- Name: chat_attachments chat_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chat_attachments
    ADD CONSTRAINT chat_attachments_pkey PRIMARY KEY (attachment_id);


--
-- TOC entry 4970 (class 2606 OID 17272)
-- Name: chats chats_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chats
    ADD CONSTRAINT chats_pkey PRIMARY KEY (chat_id);


--
-- TOC entry 4994 (class 2606 OID 17381)
-- Name: group_members group_members_group_user_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_group_user_key UNIQUE (group_id, user_id);


--
-- TOC entry 4996 (class 2606 OID 17379)
-- Name: group_members group_members_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_pkey PRIMARY KEY (id);


--
-- TOC entry 5000 (class 2606 OID 17427)
-- Name: group_message_attachments group_message_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_attachments
    ADD CONSTRAINT group_message_attachments_pkey PRIMARY KEY (id);


--
-- TOC entry 4998 (class 2606 OID 17404)
-- Name: group_messages group_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_messages
    ADD CONSTRAINT group_messages_pkey PRIMARY KEY (id);


--
-- TOC entry 4992 (class 2606 OID 17364)
-- Name: groups groups_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (id);


--
-- TOC entry 4972 (class 2606 OID 17274)
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- TOC entry 4974 (class 2606 OID 17276)
-- Name: password_resets password_resets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_pkey PRIMARY KEY (id);


--
-- TOC entry 4976 (class 2606 OID 17278)
-- Name: screenshots screenshots_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.screenshots
    ADD CONSTRAINT screenshots_pkey PRIMARY KEY (id);


--
-- TOC entry 4980 (class 2606 OID 17280)
-- Name: subtasks subtasks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subtasks
    ADD CONSTRAINT subtasks_pkey PRIMARY KEY (id);


--
-- TOC entry 4982 (class 2606 OID 17282)
-- Name: task_assignees task_assignees_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees
    ADD CONSTRAINT task_assignees_pkey PRIMARY KEY (id);


--
-- TOC entry 4984 (class 2606 OID 17284)
-- Name: task_assignees task_assignees_task_id_user_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees
    ADD CONSTRAINT task_assignees_task_id_user_id_key UNIQUE (task_id, user_id);


--
-- TOC entry 4986 (class 2606 OID 17286)
-- Name: tasks tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_pkey PRIMARY KEY (id);


--
-- TOC entry 4988 (class 2606 OID 17288)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- TOC entry 4990 (class 2606 OID 17290)
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- TOC entry 4977 (class 1259 OID 17291)
-- Name: idx_subtasks_member_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subtasks_member_id ON public.subtasks USING btree (member_id);


--
-- TOC entry 4978 (class 1259 OID 17292)
-- Name: idx_subtasks_task_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subtasks_task_id ON public.subtasks USING btree (task_id);


--
-- TOC entry 5001 (class 2606 OID 17293)
-- Name: attendance attendance_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance
    ADD CONSTRAINT attendance_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- TOC entry 5002 (class 2606 OID 17298)
-- Name: chat_attachments chat_attachments_chat_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chat_attachments
    ADD CONSTRAINT chat_attachments_chat_id_fkey FOREIGN KEY (chat_id) REFERENCES public.chats(chat_id) ON DELETE CASCADE;


--
-- TOC entry 5013 (class 2606 OID 17382)
-- Name: group_members fk_group_members_group; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- TOC entry 5014 (class 2606 OID 17387)
-- Name: group_members fk_group_members_user; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5015 (class 2606 OID 17405)
-- Name: group_messages fk_group_messages_group; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_messages
    ADD CONSTRAINT fk_group_messages_group FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- TOC entry 5016 (class 2606 OID 17410)
-- Name: group_messages fk_group_messages_sender; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_messages
    ADD CONSTRAINT fk_group_messages_sender FOREIGN KEY (sender_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5017 (class 2606 OID 17428)
-- Name: group_message_attachments fk_group_msg_attach_msg; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_attachments
    ADD CONSTRAINT fk_group_msg_attach_msg FOREIGN KEY (message_id) REFERENCES public.group_messages(id) ON DELETE CASCADE;


--
-- TOC entry 5003 (class 2606 OID 17303)
-- Name: notifications notifications_recipient_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_recipient_fkey FOREIGN KEY (recipient) REFERENCES public.users(id);


--
-- TOC entry 5004 (class 2606 OID 17308)
-- Name: notifications notifications_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE SET NULL;


--
-- TOC entry 5005 (class 2606 OID 17313)
-- Name: screenshots screenshots_attendance_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.screenshots
    ADD CONSTRAINT screenshots_attendance_id_fkey FOREIGN KEY (attendance_id) REFERENCES public.attendance(id);


--
-- TOC entry 5006 (class 2606 OID 17318)
-- Name: screenshots screenshots_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.screenshots
    ADD CONSTRAINT screenshots_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- TOC entry 5007 (class 2606 OID 17323)
-- Name: subtasks subtasks_member_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subtasks
    ADD CONSTRAINT subtasks_member_id_fkey FOREIGN KEY (member_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5008 (class 2606 OID 17328)
-- Name: subtasks subtasks_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subtasks
    ADD CONSTRAINT subtasks_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;


--
-- TOC entry 5009 (class 2606 OID 17333)
-- Name: task_assignees task_assignees_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees
    ADD CONSTRAINT task_assignees_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;


--
-- TOC entry 5010 (class 2606 OID 17338)
-- Name: task_assignees task_assignees_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees
    ADD CONSTRAINT task_assignees_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5011 (class 2606 OID 17343)
-- Name: tasks tasks_assigned_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_assigned_to_fkey FOREIGN KEY (assigned_to) REFERENCES public.users(id);


--
-- TOC entry 5012 (class 2606 OID 17348)
-- Name: tasks tasks_reviewed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES public.users(id);


-- Completed on 2026-02-08 23:53:04

--
-- PostgreSQL database dump complete
--

\unrestrict GOKy15aotHGKGJhaDEGiyHU35Ur2ExtmhvfgTGphlJUyi7chvcgDgxdw3388ilW

