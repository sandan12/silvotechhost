'use client';

import { useEffect } from 'react';

export default function RootPage() {
  useEffect(() => { window.location.replace('/pl/'); }, []);
  return <main style={{padding:'2rem',fontFamily:'sans-serif'}}><a href="/pl/">Przejdź do strony SilvoTech</a></main>;
}
