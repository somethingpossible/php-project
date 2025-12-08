create table forum_comments
(
    id         int auto_increment comment '评论ID'
        primary key,
    post_id    int                                 not null comment '关联的帖子ID',
    user_id    int                                 not null comment '评论用户ID（关联users表）',
    username   varchar(50)                         not null comment '评论用户名',
    content    text                                not null comment '评论内容',
    created_at timestamp default CURRENT_TIMESTAMP not null comment '评论时间'
)
    comment '论坛帖子评论表' engine = InnoDB
                             charset = utf8mb4;

create index idx_comments_post_id
    on forum_comments (post_id);

create index idx_comments_user_id
    on forum_comments (user_id);

create table forum_posts
(
    id            int auto_increment comment '帖子ID'
        primary key,
    user_id       int                                     not null comment '发布用户ID（关联users表）',
    username      varchar(50)                             not null comment '发布用户名',
    title         varchar(255)                            not null comment '帖子标题',
    content       text                                    not null comment '帖子内容',
    created_at    timestamp     default CURRENT_TIMESTAMP not null comment '发布时间',
    updated_at    timestamp     default CURRENT_TIMESTAMP not null on update CURRENT_TIMESTAMP comment '最后更新时间（含回复时间）',
    comment_count int           default 0                 null comment '评论数',
    images        varchar(1000) default ''                null comment '帖子图片路径（相对路径，逗号分隔）'
)
    comment '乒乓论坛帖子表' engine = InnoDB
                             charset = utf8mb4;

create index idx_posts_updated_at
    on forum_posts (updated_at);

create table table_reservations
(
    id         int auto_increment
        primary key,
    status     enum ('empty', 'one', 'full') default 'empty'           not null comment '球桌状态：empty-空桌，one-1人，full-满员',
    created_at timestamp                     default CURRENT_TIMESTAMP not null,
    updated_at timestamp                     default CURRENT_TIMESTAMP not null on update CURRENT_TIMESTAMP
)
    comment '乒乓球桌信息表' engine = InnoDB
                             charset = utf8mb4;

create table reservations
(
    id         int auto_increment
        primary key,
    table_id   int                                 not null comment '关联的球桌ID',
    user_name  varchar(50)                         not null comment '预约人姓名',
    duration   int       default 1                 null comment '预约时长（小时）',
    created_at timestamp default CURRENT_TIMESTAMP not null,
    constraint reservations_ibfk_1
        foreign key (table_id) references table_reservations (id)
            on delete cascade
)
    comment '预约记录表' engine = InnoDB
                         charset = utf8mb4;

create index table_id
    on reservations (table_id);

create table tables
(
    id           int auto_increment comment '球桌ID'
        primary key,
    table_number varchar(10)                                             not null comment '球桌编号',
    status       enum ('empty', 'one', 'full') default 'empty'           not null comment '状态：空桌/1人/满员',
    created_at   timestamp                     default CURRENT_TIMESTAMP not null comment '创建时间',
    updated_at   timestamp                     default CURRENT_TIMESTAMP not null on update CURRENT_TIMESTAMP comment '更新时间',
    constraint uk_table_number
        unique (table_number)
)
    comment '球桌信息表' engine = InnoDB
                         charset = utf8mb4;

create table users
(
    id       int auto_increment
        primary key,
    username varchar(50)  not null,
    password varchar(255) not null,
    sex      varchar(10)  not null,
    age      varchar(3)   not null,
    approach varchar(15)  not null,
    constraint username
        unique (username)
);

