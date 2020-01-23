<?php

namespace App\Entity\Report;


class Report
{
	public $a;
    public $a1;
    
    //2
    public $b;
    
    //3
    public $c;
    
    //4
    public $d;
    
    //5
    public $e;
    
    //6
    public $f;

    //7
    public $g;
    
    //8
    public $h;
    
    //9
    public $i;
    
    //10
    public $j;
    
    //11
    public $k;
    
    //12
    public $l;
    
    //13
    public $m;
    
    public $n;
    
    //15
    public $o;
    
    //16
    public $p;
    /**
     * Splošna davčna olajšava
     */
    public $q1;
    public $q2;
    public $q3;
    
    //17
    public $q;
    
    //18
    public $r;
    
    //19
    public $s;
    
    //20
    public $t;
    
    //21
    public $u;
    
    //22
    public $v;
    
    //23
    public $w;
    
    //24
    public $x;
    
    /**
     * Obračunana predhodna akontacija
     */
    public $y;
    
    //26
    public $z;
    
    public function recalculate()
    {
    	$this->a = $this->a1;
    	
    	//DAVČNO PRIZNANI PRIHODKI (1 – 2 + 3) 
    	$this->d = $this->a - $this->b + $this->c;
    	
    	//DAVČNO PRIZNANI ODHODKI (5 – 6 + 7) 
    	$this->h = $this->e - $this->f + $this->g;
    	
    	//RAZLIKA med davčno priznanimi prihodki in odhodki (4 – 8)
    	$this->i = $this->d - $this->h > 0 ? $this->d - $this->h : 0;
    	
    	//RAZLIKA med davčno priznanimi odhodki in prihodki (8 – 4)
    	$this->j = $this->h - $this->d > 0 ? $this->h - $this->d : 0 ;
    	
    	//DAVČNA OSNOVA (9 + 11 + 12) ali (11+12-10); če je > 0
    	$this->m = $this->i>0?($this->i + $this->k + $this->l):($this->k + $this->l - $this->j);
    	
    	//OSNOVA ZA DOHODNINO (izračun dohodnine na letni ravni) (13-15)
    	$this->p = $this->m - $this->o;   
    	
    	// 	Olajšave, ki zmanjšujejo osnovo za dohodnino (17.1+17.2+17.3)
    	$this->q = $this->q1 + $this->q2 +$this->q3;
    	
    	// 	OSNOVA ZA AKONTACIJO DOHODNINE (16-17); če je > 0
    	$this->s = $this->p - $this->q > 0 ? $this->p - $this->q : 0;    	    	
    	
    }
}
