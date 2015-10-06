
drop table if exists Person;
drop table if exists Residence;
drop table if exists Address;
drop table if exists StateProvince;
drop table if exists Country;

create table Country
(
    Code varchar(2) not null primary key,
    Name varchar(128) not null
);

create table StateProvince
(
    Code varchar(2) not null primary key,
    CountryCode varchar(2) not null,
    Name varchar(128) not null,
    foreign key fk_Country(CountryCode) references Country(Code)
);

create table Address
(
    AddressID int not null primary key auto_increment,
    Street varchar(256) not null,
    Subdivision varchar(32) null,
    City varchar(128) not null,
    StateProvinceCode varchar(2) not null,
    PostalCode varchar(16) not null,
    foreign key fk_StateProvince(StateProvinceCode) references StateProvince(Code)
        on update cascade
        on delete cascade
);

create table Residence
(
    ResidenceID int not null primary key auto_increment,
    AddressID int not null,
    foreign key fk_Address(AddressID) references Address(AddressID)
        on update cascade
        on delete cascade
);

create table Person
(
    PersonID int not null primary key auto_increment,
    ResidenceID int null,
    Salutation varchar(4) null,
    FirstName varchar(64) not null,
    MiddleName varchar(64) null,
    LastName varchar(64) not null,
    Cadency varchar(4) null,
    EmailAddress varchar(256) not null,
    PhoneNumber varchar(80) null,
    GenderCode char not null,
    foreign key fk_Residence(ResidenceID) references Residence(ResidenceID)
        on update cascade
        on delete cascade
);

insert into Country (Code, Name)
values
    ('US', 'United States of America'),
    ('CA', 'Canada');

insert into StateProvince (Code, CountryCode, Name)
values
    ('AK', 'US', 'Alaska'),
    ('AL', 'US', 'Alabama'),
    ('AR', 'US', 'Arkansas'),
    ('AZ', 'US', 'Arizona'),
    ('CA', 'US', 'California'),
    ('CO', 'US', 'Colorado'),
    ('CT', 'US', 'Connecticut'),
    ('DE', 'US', 'Delaware'),
    ('FL', 'US', 'Florida'),
    ('GA', 'US', 'Georgia'),
    ('HI', 'US', 'Hawaii'),
    ('IA', 'US', 'Iowa'),
    ('ID', 'US', 'Idaho'),
    ('IL', 'US', 'Illinois'),
    ('IN', 'US', 'Indiana'),
    ('KS', 'US', 'Kansas'),
    ('KY', 'US', 'Kentucky'),
    ('LA', 'US', 'Louisiana'),
    ('MA', 'US', 'Massachusetts'),
    ('MD', 'US', 'Maryland'),
    ('ME', 'US', 'Maine'),
    ('MI', 'US', 'Michigan'),
    ('MN', 'US', 'Minnesota'),
    ('MO', 'US', 'Missouri'),
    ('MS', 'US', 'Mississippi'),
    ('MT', 'US', 'Montana'),
    ('NC', 'US', 'North Carolina'),
    ('ND', 'US', 'North Dakota'),
    ('NE', 'US', 'Nebraska'),
    ('NH', 'US', 'New Hampshire'),
    ('NJ', 'US', 'New Jersey'),
    ('NM', 'US', 'New Mexico'),
    ('NV', 'US', 'Nevada'),
    ('NY', 'US', 'New York'),
    ('OH', 'US', 'Ohio'),
    ('OK', 'US', 'Oklahoma'),
    ('OR', 'US', 'Oregon'),
    ('PA', 'US', 'Pennsylvania'),
    ('RI', 'US', 'Rhode Island'),
    ('SC', 'US', 'South Carolina'),
    ('SD', 'US', 'South Dakota'),
    ('TN', 'US', 'Tennesse'),
    ('TX', 'US', 'Texas'),
    ('UT', 'US', 'Utah'),
    ('VA', 'US', 'Virginia'),
    ('VT', 'US', 'Vermont'),
    ('WA', 'US', 'Washington'),
    ('WI', 'US', 'Wisconsin'),
    ('WV', 'US', 'West Virginia'),
    ('WY', 'US', 'Wyoming'),
    ('DC', 'US', 'District of Columbia'),
    ('GU', 'US', 'Guam'),
    ('PR', 'US', 'Puerto Rico'),
    ('AB', 'CA', 'Alberta'),
    ('BC', 'CA', 'British Columbia'),
    ('MB', 'CA', 'Manitoba'),
    ('NB', 'CA', 'New Brunswick'),
    ('NL', 'CA', 'Newfoundland and Labrador'),
    ('NS', 'CA', 'Nova Scotia'),
    ('NT', 'CA', 'Northwest Territories'),
    ('NU', 'CA', 'Nunavut'),
    ('ON', 'CA', 'Ontario'),
    ('PE', 'CA', 'Prince Edward Island'),
    ('QC', 'CA', 'Quebec'),
    ('SK', 'CA', 'Saskatchewan'),
    ('YT', 'CA', 'Yukon Territory');
